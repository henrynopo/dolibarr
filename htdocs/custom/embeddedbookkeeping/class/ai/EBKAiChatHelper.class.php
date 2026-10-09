<?php
/**
 *  \file       htdocs/custom/embeddedbookkeeping/class/ai/EBKAiChatHelper.class.php
 *  \ingroup    embeddedbookkeeping
 *  \brief      Thin wrapper that calls the active EBKAiSuggester provider
 *              for free-form chat (not bound to a structured EBKEntryProposal).
 *
 *              Falls back to calling UniversalLLMAdapter directly when the
 *              provider doesn't implement a generic chat method.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiSuggester.interface.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiProviderFactory.class.php';

if (!class_exists('EBKAiChatHelper', false)) {

class EBKAiChatHelper
{
    /**
     * Why the last answer() call came back empty. '' after a successful call.
     *
     * Every failure mode used to collapse into one generic "check your config"
     * message, which is useless when the real problem is an upstream 401 or a
     * missing key: the caller now reports THIS instead.
     *
     * @var string
     */
    private static $lastError = '';

    /**
     * @return string  Empty when the previous call succeeded.
     */
    public static function lastError()
    {
        return self::$lastError;
    }

    /**
     * Name the exact constant that is missing, so the message points at one
     * field instead of "go check your settings".
     *
     * @param  stdClass $conf
     * @return string
     */
    private static function describeMissingConfig()
    {
        $provider = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_PROVIDER', 'ai_module');
        if ($provider === 'disabled') {
            return 'AI 已在配置页被关闭（EMBEDDEDBOOKKEEPING_AI_PROVIDER = disabled）。';
        }
        if ($provider === 'ebk_custom') {
            $service = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE', '');
            $key = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY', '');
            if ($service === '') {
                return '未设置 EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE（服务名）。';
            }
            if ($key === '') {
                return '未设置 EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY（'.htmlspecialchars($service, ENT_QUOTES, 'UTF-8').' 的 API Key）。';
            }
            return 'EBK 独立 LLM 配置不完整（service='.htmlspecialchars($service, ENT_QUOTES, 'UTF-8').'，key 已填，但 URL/模型不可解析）。';
        }
        if ($provider === 'ai_module') {
            $service = (string) getDolGlobalString('AI_API_SERVICE', '');
            if ($service === '') {
                return '未设置 AI_API_SERVICE —— 请先在 第三方模块 → AI → 设置 中完成核心 AI 模块的配置。';
            }
            $key = (string) getDolGlobalString('AI_API_'.strtoupper($service).'_KEY', '');
            if ($key === '') {
                return '未设置 AI_API_'.strtoupper($service).'_KEY —— 核心 AI 模块没有该服务的 Key。';
            }
            return '核心 AI 模块配置不完整（service='.htmlspecialchars($service, ENT_QUOTES, 'UTF-8').'）。';
        }
        return '未知的 provider 值：'.htmlspecialchars($provider, ENT_QUOTES, 'UTF-8').'（只能是 disabled / ai_module / ebk_custom）。';
    }

    /**
     * Answer a free-form question using the active AI provider.
     *
     * @param  EBKAiSuggester $provider
     * @param  string         $question   The user's question
     * @param  string         $contextBlock  Pre-built context (may be empty)
     * @param  string         $sysPrompt  System-level instruction
     * @param  DoliDB         $db
     * @param  stdClass       $conf
     * @param  Translate       $langs
     * @return string                  The AI's answer, or '' on failure
     */
    public static function answer($provider, $question, $contextBlock, $sysPrompt, $db, $conf, $langs)
    {
        self::$lastError = '';

        // If the provider supports free-form chat, delegate.
        if (method_exists($provider, 'chat')) {
            $out = $provider->chat($question, $contextBlock, $sysPrompt);
            if (!is_string($out) || trim($out) === '') {
                self::$lastError = 'provider '.get_class($provider).' 返回了空内容。';
                return '';
            }
            return $out;
        }

        // Otherwise, build a full prompt and call the adapter directly.
        $adapter = self::resolveAdapter($conf);
        if (!$adapter) {
            self::$lastError = self::describeMissingConfig();
            return '';
        }

        $fullUserPrompt = '';
        if ($contextBlock !== '') {
            $fullUserPrompt .= $contextBlock."\n";
        }
        $fullUserPrompt .= "【用户问题】\n{$question}";

        $raw = $adapter->generate($sysPrompt, $fullUserPrompt, 'text');
        if (!is_string($raw) || $raw === '') {
            self::$lastError = '上游模型返回了空内容（HTTP 200 但响应体为空，或被超时截断）。';
            return '';
        }
        // UniversalLLMAdapter reports upstream failures as "Error: ..." strings
        // rather than throwing, so this is where a 401 / 429 / bad model name
        // actually surfaces. Keep the upstream text: it is the only thing that
        // says WHICH failure it was.
        if (strpos($raw, 'Error:') === 0) {
            self::$lastError = '上游接口报错：'.trim(substr($raw, 6, 300));
            return '';
        }

        return trim($raw);
    }

    /**
     * Resolve the active UniversalLLMAdapter from EBKAiProviderFactory,
     * mirroring the logic in AiModuleProvider::resolveAdapter().
     *
     * @param  stdClass $conf
     * @return UniversalLLMAdapter|null
     */
    private static function resolveAdapter($conf)
    {
        if (!class_exists('UniversalLLMAdapter', false)) {
            $file = DOL_DOCUMENT_ROOT.'/ai/class/llmadapter.class.php';
            if (!is_file($file)) {
                return null;
            }
            require_once $file;
        }
        if (!function_exists('getListOfAIServices')) {
            $file = DOL_DOCUMENT_ROOT.'/ai/lib/ai.lib.php';
            if (!is_file($file)) {
                return null;
            }
            require_once $file;
        }

        $arrayofai = getListOfAIServices();
        if (!is_array($arrayofai) || empty($arrayofai)) {
            return null;
        }

        $provider = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_PROVIDER', 'ai_module');

        if ($provider === 'ebk_custom') {
            return self::resolveAdapterEbkCustom($conf, $arrayofai);
        }
        return self::resolveAdapterAiModule($conf, $arrayofai);
    }

    private static function resolveAdapterAiModule($conf, $arrayofai)
    {
        $service = (string) getDolGlobalString('AI_API_SERVICE', 'chatgpt');
        if (!isset($arrayofai[$service])) {
            return null;
        }
        $key = (string) getDolGlobalString('AI_API_'.strtoupper($service).'_KEY', '');
        if ($key === '' && in_array($service, array('chatgpt', 'groq', 'mistral'), true)) {
            return null;
        }
        $url = (string) getDolGlobalString('AI_API_'.strtoupper($service).'_URL', $arrayofai[$service]['url']);
        $modelDefault = isset($arrayofai[$service]['textgeneration']['default'])
            ? (string) $arrayofai[$service]['textgeneration']['default'] : '';
        $model = (string) getDolGlobalString('AI_API_'.strtoupper($service).'_MODEL_TEXT', $modelDefault);
        return new UniversalLLMAdapter($service, $key, $url, $model, 90);
    }

    private static function resolveAdapterEbkCustom($conf, $arrayofai)
    {
        $service = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE', 'chatgpt');
        if (!isset($arrayofai[$service])) {
            return null;
        }
        $key = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY', '');
        if ($key === '' && in_array($service, array('chatgpt', 'groq', 'mistral'), true)) {
            return null;
        }
        $urlDefault = isset($arrayofai[$service]['url']) ? (string) $arrayofai[$service]['url'] : '';
        $url = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_URL', $urlDefault);
        $modelDefault = isset($arrayofai[$service]['textgeneration']['default'])
            ? (string) $arrayofai[$service]['textgeneration']['default'] : '';
        $model = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_MODEL', $modelDefault);
        return new UniversalLLMAdapter($service, $key, $url, $model, 90);
    }
}

} // if (!class_exists('EBKAiChatHelper', false))
