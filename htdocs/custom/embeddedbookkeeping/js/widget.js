/* EmbeddedBookkeeping / System Accounting - Floating AI Assistant */
/* Pure ES5: no arrow functions, no template literals, no let/const.
   No fetch() either - XMLHttpRequest keeps the widest browser support.

   STYLING CONTRACT (this bit bit us once, don't regress it):
   everything that lives INSIDE the message list is styled through CLASSES
   (ebk-ai-*), never through ID selectors. Elements that are unique - the FAB
   button, the panel, the header, the textarea - keep their id and may be
   styled with #id. Mixing the two up is what left the bubbles unstyled. */
(function () {
    'use strict';

    var endpoint = '';
    var token    = '';
    var docId    = 0;
    var docType  = '';
    var docRef   = '';
    var docUrl   = '';
    var pageCtx  = {};
    var selText  = '';
    var lang     = 'zh';

    var STR = {
        btn_title: 'AI 助手',
        hdr_title: 'AI 助手',
        btn_close: '关闭',
        placeholder_zh: '输入问题… (Enter 发送，Shift+Enter 换行)',
        placeholder_en: 'Ask a question... (Enter to send, Shift+Enter for newline)',
        btn_send: '发送',
        av_ai: 'AI',
        av_user: '我',
        empty_title_zh: '我是 Dolibarr AI 助手',
        empty_title_en: "I'm the Dolibarr AI Assistant",
        empty_hint_zh: '直接输入问题即可。打开发票或费用报销单卡片时，我会读取该单据的明细，并对照本公司的会计科目表给出记账建议。',
        empty_hint_en: 'Just type your question. On an invoice or expense report card I read the document lines and suggest accounts from YOUR chart of accounts.',
        typing_zh: 'AI 思考中…',
        typing_en: 'AI is thinking...',
        err_net_zh: '⚠ 网络错误 ',
        err_net_en: '⚠ Network error ',
        err_fallback_zh: '⚠ 无法回答，请检查 AI 配置。',
        err_fallback_en: '⚠ Unable to answer. Please check the AI configuration.',
        grip: '拖动调整大小',
        pg_accountancy_zh: '会计模块', pg_accountancy_en: 'Accounting',
        pg_bookkeeping_zh: '会计记账', pg_bookkeeping_en: 'Bookkeeping',
        pg_closure_zh: '会计结账', pg_closure_en: 'Accounting Closure',
        pg_journal_zh: '会计日记账', pg_journal_en: 'Accounting Journal',
        pg_export_zh: '会计导出', pg_export_en: 'Accounting Export',
        pg_admin_zh: '会计管理', pg_admin_en: 'Accounting Admin',
        pg_ebk_setup_zh: 'EBK 配置', pg_ebk_setup_en: 'EBK Setup',
        pg_ebk_entry_zh: '记账分录', pg_ebk_entry_en: 'Bookkeeping Entry',
        pg_invoice_zh: '发票卡片', pg_invoice_en: 'Invoice Card',
        pg_expense_zh: '费用报销卡片', pg_expense_en: 'Expense Report Card',
        pg_generic_zh: '通用', pg_generic_en: 'General'
    };

    function T(key) {
        var en = (lang === 'en');
        switch (key) {
            case 'btn_title':      return STR.btn_title;
            case 'hdr_title':      return STR.hdr_title;
            case 'btn_close':      return STR.btn_close;
            case 'placeholder':    return en ? STR.placeholder_en : STR.placeholder_zh;
            case 'btn_send':       return STR.btn_send;
            case 'av_ai':          return STR.av_ai;
            case 'av_user':        return STR.av_user;
            case 'empty_title':    return en ? STR.empty_title_en : STR.empty_title_zh;
            case 'empty_hint':     return en ? STR.empty_hint_en : STR.empty_hint_zh;
            case 'typing':         return en ? STR.typing_en : STR.typing_zh;
            case 'err_net':        return en ? STR.err_net_en : STR.err_net_zh;
            case 'err_fallback':   return en ? STR.err_fallback_en : STR.err_fallback_zh;
            case 'grip':           return STR.grip;
            case 'pg_bookkeeping': return en ? STR.pg_bookkeeping_en : STR.pg_bookkeeping_zh;
            case 'pg_closure':     return en ? STR.pg_closure_en : STR.pg_closure_zh;
            case 'pg_journal':     return en ? STR.pg_journal_en : STR.pg_journal_zh;
            case 'pg_export':      return en ? STR.pg_export_en : STR.pg_export_zh;
            case 'pg_accountancy': return en ? STR.pg_accountancy_en : STR.pg_accountancy_zh;
            case 'pg_admin':       return en ? STR.pg_admin_en : STR.pg_admin_zh;
            case 'pg_ebk_setup':   return en ? STR.pg_ebk_setup_en : STR.pg_ebk_setup_zh;
            case 'pg_ebk_entry':   return en ? STR.pg_ebk_entry_en : STR.pg_ebk_entry_zh;
            case 'pg_generic':     return en ? STR.pg_generic_en : STR.pg_generic_zh;
            default:               return key;
        }
    }

    function escHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* Minimal, injection-safe markup rendering of the AI answer.
       The text is HTML-escaped FIRST, so everything below only ever
       introduces formatting tags of our own. Supports **bold**, `code`
       and "- " bullets - enough for accounting tables without pulling
       in a markdown library. */
    function renderRich(text) {
        var html = escHtml(text);

        html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');
        html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');

        var lines = html.split(/\r?\n/);
        var out = '';
        var i;
        var inList = false;
        for (i = 0; i < lines.length; i++) {
            var ln = lines[i];
            var t = ln.replace(/^\s+/, '');
            if (/^\s*[-•]\s+/.test(ln)) {
                if (!inList) { out += '<ul>'; inList = true; }
                out += '<li>' + t.replace(/^\s*[-•]\s+/, '') + '</li>';
                continue;
            }
            if (inList) { out += '</ul>'; inList = false; }
            if (t === '') {
                out += '<div class="ebk-ai-gap"></div>';
            } else {
                out += '<div class="ebk-ai-line">' + ln + '</div>';
            }
        }
        if (inList) out += '</ul>';
        return out;
    }

    function css() {
        var p = '#ebk-ai-';
        /* NOTE: the root wrapper is #ebk-ai - it must be spelled out in full
           here. Using p alone would emit "#ebk-ai-{...}", a syntactically
           valid selector that matches nothing, silently killing position:fixed
           and z-index and dropping the whole widget into the document flow. */
        return '#ebk-ai{position:fixed;bottom:20px;right:20px;z-index:99999;' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",Roboto,"Helvetica Neue",Arial,sans-serif;' +
            'font-size:14px;line-height:1.6;color:#1f2328;}' +

            /* ---------- floating action button ---------- */
            p + 'btn{width:54px;height:54px;border-radius:27px;background:linear-gradient(135deg,#1e5aa8,#1565c0);' +
            'color:#fff;border:none;cursor:pointer;box-shadow:0 6px 18px rgba(21,101,192,.45);' +
            'display:flex;align-items:center;justify-content:center;transition:transform .15s,box-shadow .15s;outline:none;}' +
            p + 'btn:hover{transform:scale(1.08);box-shadow:0 8px 24px rgba(21,101,192,.55);}' +
            p + 'btn svg{width:26px;height:26px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}' +

            /* ---------- panel ---------- */
            p + 'panel{position:absolute;bottom:66px;right:0;width:400px;height:min(620px,calc(100vh - 130px));' +
            'background:#eaeef4;border:1px solid #d0d8e4;border-radius:14px;box-shadow:0 12px 48px rgba(15,30,60,.22);' +
            'display:none;flex-direction:column;overflow:hidden;}' +
            p + 'panel.open{display:flex;}' +

            /* ---------- header ---------- */
            p + 'hdr{padding:10px 12px;background:linear-gradient(135deg,#1e5aa8,#1565c0);color:#fff;' +
            'display:flex;align-items:center;justify-content:space-between;flex-shrink:0;' +
            'cursor:move;user-select:none;-webkit-user-select:none;}' +
            p + 'hdr-left{display:flex;align-items:center;gap:9px;min-width:0;}' +
            p + 'hdr-ico{width:30px;height:30px;flex:0 0 30px;border-radius:50%;background:rgba(255,255,255,.22);' +
            'display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;letter-spacing:.3px;}' +
            p + 'hdr-text{display:flex;flex-direction:column;min-width:0;}' +
            p + 'hdr-title{font-weight:600;font-size:14px;line-height:1.25;}' +
            p + 'hdr-page{font-size:11px;opacity:.85;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}' +
            p + 'close{background:none;border:none;color:#fff;cursor:pointer;font-size:20px;line-height:1;padding:0 2px;opacity:.85;}' +
            p + 'close:hover{opacity:1;}' +

            /* ---------- selection banner ---------- */
            p + 'ctx{padding:6px 12px;background:#fff8e1;border-bottom:1px solid #ffe9a8;color:#7a5b00;font-size:12px;display:none;flex-shrink:0;}' +

            /* ---------- message list ---------- */
            p + 'body{flex:1;overflow-y:auto;padding:14px 12px;background:#eaeef4;' +
            'display:flex;flex-direction:column;gap:12px;}' +
            p + 'body::-webkit-scrollbar{width:7px;}' +
            p + 'body::-webkit-scrollbar-thumb{background:#c2ccda;border-radius:4px;}' +

            /* one row = avatar + bubble column. AI left, user right (mirrored). */
            '.ebk-ai-msg{display:flex;align-items:flex-start;gap:8px;max-width:84%;}' +
            '.ebk-ai-msg.user{align-self:flex-end;flex-direction:row-reverse;}' +

            '.ebk-ai-av{width:32px;height:32px;border-radius:50%;flex:0 0 32px;margin-top:2px;' +
            'display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;}' +
            '.ebk-ai-msg.ai .ebk-ai-av{background:#5b6b7f;}' +
            '.ebk-ai-msg.user .ebk-ai-av{background:#0d47a1;}' +

            '.ebk-ai-stack{min-width:0;display:flex;flex-direction:column;}' +
            '.ebk-ai-msg.ai .ebk-ai-stack{align-items:flex-start;}' +
            '.ebk-ai-msg.user .ebk-ai-stack{align-items:flex-end;}' +

            /* the bubbles: solid colour, opposite sides, little tail */
            '.ebk-ai-bubble{position:relative;padding:9px 13px;border-radius:14px;word-break:break-word;' +
            'white-space:normal;font-size:13.5px;line-height:1.62;box-shadow:0 1px 1px rgba(16,32,64,.10);}' +
            '.ebk-ai-msg.ai .ebk-ai-bubble{background:#ffffff;border:1px solid #dbe2ec;color:#1f2328;border-top-left-radius:4px;}' +
            '.ebk-ai-msg.user .ebk-ai-bubble{background:#1565c0;border:1px solid #1565c0;color:#fff;border-top-right-radius:4px;}' +

            /* tails */
            '.ebk-ai-bubble:before{content:"";position:absolute;top:9px;width:0;height:0;border:6px solid transparent;}' +
            '.ebk-ai-msg.ai .ebk-ai-bubble:before{left:-11px;border-right-color:#ffffff;}' +
            '.ebk-ai-msg.user .ebk-ai-bubble:before{right:-11px;border-left-color:#1565c0;}' +

            '.ebk-ai-ts{font-size:10.5px;color:#8b95a3;margin:4px 3px 0;}' +
            '.ebk-ai-msg.user .ebk-ai-ts{color:#7d8794;}' +

            /* inner formatting of the AI answer (classes only) */
            '.ebk-ai-line{margin:1px 0;}' +
            '.ebk-ai-gap{height:6px;}' +
            '.ebk-ai-bubble ul{margin:4px 0;padding-left:18px;}' +
            '.ebk-ai-bubble li{margin:2px 0;}' +
            '.ebk-ai-bubble code{background:#eef1f6;border-radius:4px;padding:1px 5px;' +
            'font-family:Consolas,Monaco,monospace;font-size:12px;}' +
            '.ebk-ai-bubble strong{font-weight:600;}' +
            '.ebk-ai-msg.user .ebk-ai-bubble code{background:rgba(255,255,255,.2);color:#fff;}' +
            '.ebk-ai-msg.user .ebk-ai-bubble strong{color:#fff;}' +

            /* ---------- typing indicator ---------- */
            '.ebk-ai-typing{display:flex;align-items:center;gap:5px;padding:11px 14px;background:#fff;' +
            'border:1px solid #dbe2ec;border-radius:14px;border-top-left-radius:4px;width:max-content;}' +
            '.ebk-ai-dot{width:6px;height:6px;border-radius:50%;background:#8b95a3;animation:ebk-pulse 1.2s infinite;}' +
            '.ebk-ai-lab{font-size:12px;color:#8b95a3;margin-left:3px;}' +

            /* ---------- empty state ---------- */
            '.ebk-ai-empty{background:#fff;border:1px solid #dbe2ec;border-radius:12px;padding:18px 16px;text-align:center;}' +
            '.ebk-ai-empty-t{font-weight:600;color:#1f2328;margin-bottom:8px;font-size:14px;}' +
            '.ebk-ai-empty-h{font-size:12.5px;line-height:1.8;color:#5b6b7f;}' +
            '.ebk-ai-empty-h strong{color:#1565c0;}' +

            /* ---------- composer ---------- */
            p + 'foot{padding:10px 12px;background:#fff;border-top:1px solid #e3e8ef;display:flex;gap:8px;align-items:flex-end;flex-shrink:0;}' +
            p + 'input{flex:1;border:1px solid #cbd3de;border-radius:10px;padding:8px 10px;resize:none;font-size:13px;' +
            'outline:none;max-height:96px;overflow-y:auto;min-height:38px;font-family:inherit;line-height:1.5;background:#fff;color:#1f2328;}' +
            p + 'input:focus{border-color:#1565c0;box-shadow:0 0 0 2px rgba(21,101,192,.14);}' +
            p + 'send{background:#1565c0;color:#fff;border:none;border-radius:10px;padding:9px 15px;cursor:pointer;' +
            'font-size:13px;font-weight:600;flex-shrink:0;}' +
            p + 'send:hover{background:#0d47a1;}' +
            p + 'send:disabled{background:#b9c2ce;cursor:default;}' +

            /* ---------- drag / resize ---------- */
            /* The grip sits in the panel's bottom-right corner. When the panel is
               still anchored (default) the panel is position:absolute inside the
               fixed wrapper; once dragged it gets inline position:fixed - the grip
               works either way because both establish a containing block. */
            p + 'grip{position:absolute;right:0;bottom:0;width:18px;height:18px;cursor:nwse-resize;z-index:5;' +
            'background:linear-gradient(135deg,transparent 50%,#b9c2ce 50%,#b9c2ce 62%,transparent 62%,' +
            'transparent 74%,#b9c2ce 74%,#b9c2ce 86%,transparent 86%);border-bottom-right-radius:13px;}' +
            p + 'grip:hover{background:linear-gradient(135deg,transparent 50%,#8b95a3 50%,#8b95a3 62%,transparent 62%,' +
            'transparent 74%,#8b95a3 74%,#8b95a3 86%,transparent 86%);}' +
            '@keyframes ebk-pulse{0%,80%,100%{opacity:.35;transform:scale(.8);}40%{opacity:1;transform:scale(1);}}';
    }

    function buildUI() {
        var wrap = document.createElement('div');
        wrap.id = 'ebk-ai';
        wrap.innerHTML =
            '<style>' + css() + '</style>' +

            '<button id="ebk-ai-btn" title="' + escHtml(T('btn_title')) + '">' +
            '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>' +
            '</button>' +

            '<div id="ebk-ai-panel">' +
            '<div id="ebk-ai-hdr">' +
            '<div id="ebk-ai-hdr-left">' +
            '<span id="ebk-ai-hdr-ico">AI</span>' +
            '<span id="ebk-ai-hdr-text">' +
            '<span id="ebk-ai-hdr-title">' + escHtml(T('hdr_title')) + '</span>' +
            '<span id="ebk-ai-hdr-page"></span>' +
            '</span>' +
            '</div>' +
            '<button id="ebk-ai-close" title="' + escHtml(T('btn_close')) + '">&#215;</button>' +
            '</div>' +
            '<div id="ebk-ai-ctx"></div>' +
            '<div id="ebk-ai-body"></div>' +
            '<div id="ebk-ai-foot">' +
            '<textarea id="ebk-ai-input" rows="1" placeholder="' + escHtml(T('placeholder')) + '"></textarea>' +
            '<button id="ebk-ai-send">' + escHtml(T('btn_send')) + '</button>' +
            '</div>' +
            '<div id="ebk-ai-grip" title="' + escHtml(T('grip')) + '"></div>' +
            '</div>';

        document.body.appendChild(wrap);
    }

    function detectPage() {
        var url = window.location.pathname;
        if (/accountancy/.test(url)) {
            if (/bookkeeping/.test(url)) return T('pg_bookkeeping');
            if (/closure/.test(url)) return T('pg_closure');
            if (/journal/.test(url)) return T('pg_journal');
            if (/export/.test(url)) return T('pg_export');
            if (/admin/.test(url)) return T('pg_admin');
            return T('pg_accountancy');
        }
        if (/embeddedbookkeeping/.test(url)) {
            if (/setup/.test(url)) return T('pg_ebk_setup');
            return T('pg_ebk_entry');
        }
        if (/expensereport/.test(url)) return T('pg_expense');
        if (/facture|fourn/.test(url)) return T('pg_invoice');
        return T('pg_generic');
    }

    /* ---------------------------------------------------------------------
       Panel geometry: drag / resize / persist
       ---------------------------------------------------------------------
       geom === null means "anchored": the panel uses the stylesheet defaults
       (bottom-right, above the FAB). The first drag or resize switches the
       panel to explicit inline geometry and stores it in localStorage so it
       follows the user across pages and reloads. Double-clicking the header
       drops back to anchored. All values are clamped to the viewport so the
       header can never be dragged out of reach. */

    var GEOM_KEY = 'ebk_ai_panel_geom';
    var MIN_W = 320;
    var MIN_H = 340;
    var geom = null;

    function loadGeom() {
        var raw = null;
        try {
            raw = window.localStorage.getItem(GEOM_KEY);
        } catch (e) {
            return null;   // private mode / storage disabled
        }
        if (!raw) return null;
        try {
            var g = JSON.parse(raw);
            if (!g || typeof g !== 'object') return null;
            if (typeof g.l !== 'number' || typeof g.t !== 'number') return null;
            if (typeof g.w !== 'number' || g.w < MIN_W) return null;
            if (typeof g.h !== 'number' || g.h < MIN_H) return null;
            return g;
        } catch (e) {
            return null;
        }
    }

    function saveGeom(g) {
        try {
            window.localStorage.setItem(GEOM_KEY, JSON.stringify(g));
        } catch (e) { /* storage full or blocked - geometry is still applied this page */ }
    }

    function clearGeom() {
        try {
            window.localStorage.removeItem(GEOM_KEY);
        } catch (e) { /* ignore */ }
    }

    /* Clamp into the viewport and write it onto the panel as inline geometry.
       Setting position:fixed plus explicit left/top/width/height makes the
       stylesheet's bottom/right/width/height inert, so we neutralise the two
       that could otherwise fight it. */
    function applyGeom(panel, g) {
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var w = Math.max(MIN_W, Math.min(Math.round(g.w), vw - 16));
        var h = Math.max(MIN_H, Math.min(Math.round(g.h), vh - 16));
        var l = Math.max(0, Math.min(Math.round(g.l), vw - w));
        var t = Math.max(0, Math.min(Math.round(g.t), vh - h));

        geom = { l: l, t: t, w: w, h: h };

        panel.style.position = 'fixed';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
        panel.style.left = l + 'px';
        panel.style.top = t + 'px';
        panel.style.width = w + 'px';
        panel.style.height = h + 'px';

        return geom;
    }

    function resetGeom(panel) {
        geom = null;
        clearGeom();
        panel.style.position = '';
        panel.style.right = '';
        panel.style.bottom = '';
        panel.style.left = '';
        panel.style.top = '';
        panel.style.width = '';
        panel.style.height = '';
    }

    /* Current on-screen geometry. Falls back to the rendered rect while the
       panel is still anchored, so the very first drag starts from where the
       user actually sees it. */
    function readGeom(panel) {
        if (geom) return { l: geom.l, t: geom.t, w: geom.w, h: geom.h };
        var r = panel.getBoundingClientRect();
        return { l: r.left, t: r.top, w: r.width, h: r.height };
    }

    function setDragging(on) {
        var b = document.body;
        b.style.userSelect = on ? 'none' : '';
        b.style.webkitUserSelect = on ? 'none' : '';
    }

    /* Walk up from the event target looking for a given id (ES5 - no closest). */
    function hitId(el, id) {
        while (el && el !== document) {
            if (el.id === id) return true;
            el = el.parentNode;
        }
        return false;
    }

    /* Runs `move` on every mousemove until the button is released. Listeners
       are attached lazily on the document and removed on mouseup, so a drag
       that ends outside the window still terminates. */
    function trackDrag(ev, move) {
        ev.preventDefault();
        setDragging(true);

        function onMove(e) {
            move(e);
        }
        function onUp() {
            document.removeEventListener('mousemove', onMove, false);
            document.removeEventListener('mouseup', onUp, false);
            setDragging(false);
            if (geom) saveGeom(geom);
        }
        document.addEventListener('mousemove', onMove, false);
        document.addEventListener('mouseup', onUp, false);
    }

    function enableDrag(panel, hdr) {
        /* Drag from the header. The close button must stay clickable. */
        hdr.addEventListener('mousedown', function (ev) {
            if (ev.button !== 0) return;
            if (hitId(ev.target, 'ebk-ai-close')) return;

            var g0 = readGeom(panel);
            /* Pin to the starting spot first, so the panel does not jump on
               the first mouse move (the anchored layout has no left/top yet). */
            applyGeom(panel, { l: g0.l, t: g0.t, w: g0.w, h: g0.h });
            var sx = ev.clientX;
            var sy = ev.clientY;

            trackDrag(ev, function (e) {
                applyGeom(panel, {
                    l: g0.l + (e.clientX - sx),
                    t: g0.t + (e.clientY - sy),
                    w: g0.w,
                    h: g0.h
                });
            });
        });

        /* Double-click the header to go back to the default corner anchor. */
        hdr.addEventListener('dblclick', function (ev) {
            if (hitId(ev.target, 'ebk-ai-close')) return;
            resetGeom(panel);
        });
    }

    function enableResize(panel, grip) {
        grip.addEventListener('mousedown', function (ev) {
            if (ev.button !== 0) return;
            ev.stopPropagation();

            var g0 = readGeom(panel);
            /* Resizing an anchored panel has to pin it first - otherwise the
               stylesheet width/height would win over the mouse. */
            applyGeom(panel, { l: g0.l, t: g0.t, w: g0.w, h: g0.h });
            var sx = ev.clientX;
            var sy = ev.clientY;

            trackDrag(ev, function (e) {
                applyGeom(panel, {
                    l: g0.l,
                    t: g0.t,
                    w: g0.w + (e.clientX - sx),
                    h: g0.h + (e.clientY - sy)
                });
            });
        });
    }

    /* ---------------------------------------------------------------------
       Open / close state
       ---------------------------------------------------------------------
       Only the open/closed FLAG is remembered, never the conversation: the
       messages live in the DOM and are wiped every time the panel is closed,
       so reopening always starts from a clean slate. The flag exists so that
       navigating to another page (each one is a fresh full page load that
       re-injects the widget) brings the panel back open instead of looking
       like the assistant vanished mid-task. */

    var OPEN_KEY = 'ebk_ai_panel_open';

    function loadOpen() {
        try {
            return window.localStorage.getItem(OPEN_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function saveOpen(isOpen) {
        try {
            if (isOpen) window.localStorage.setItem(OPEN_KEY, '1');
            else window.localStorage.removeItem(OPEN_KEY);
        } catch (e) { /* storage blocked - state simply does not survive navigation */ }
    }

    function clearBody(body) {
        while (body.children.length) {
            body.removeChild(body.children[0]);
        }
    }

    function openPanel(panel, body, input) {
        panel.classList.add('open');
        saveOpen(true);
        if (!body.children.length) appendEmpty();
        input.focus();
    }

    /* Closing discards the conversation - per the agreed behaviour. */
    function closePanel(panel, body) {
        panel.classList.remove('open');
        saveOpen(false);
        clearBody(body);
    }

    function wire() {
        var btn    = document.getElementById('ebk-ai-btn');
        var panel  = document.getElementById('ebk-ai-panel');
        var close  = document.getElementById('ebk-ai-close');
        var body   = document.getElementById('ebk-ai-body');
        var input  = document.getElementById('ebk-ai-input');
        var send   = document.getElementById('ebk-ai-send');
        var ctxBar = document.getElementById('ebk-ai-ctx');
        var hdr    = document.getElementById('ebk-ai-hdr');
        var grip   = document.getElementById('ebk-ai-grip');

        /* Restore the user's dragged/resized placement before anything is
           measured, so the first interaction starts from the stored geometry. */
        var saved = loadGeom();
        if (saved) applyGeom(panel, saved);

        enableDrag(panel, hdr);
        enableResize(panel, grip);

        /* Re-open automatically if it was open when the user left the page.
           The conversation itself is NOT restored - it is intentionally wiped
           on close, so the panel comes back open but empty. */
        if (loadOpen()) {
            openPanel(panel, body, input);
        }

        /* Keep the panel reachable if the browser window is resized smaller. */
        window.addEventListener('resize', function () {
            if (geom) applyGeom(panel, geom);
        });

        var pageName = detectPage();
        document.getElementById('ebk-ai-hdr-page').textContent =
            docRef ? pageName + ' · ' + docRef : pageName;

        input.addEventListener('input', function () {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 96) + 'px';
        });

        btn.addEventListener('click', function () {
            if (panel.classList.contains('open')) {
                closePanel(panel, body);
            } else {
                openPanel(panel, body, input);
            }
        });

        close.addEventListener('click', function () {
            closePanel(panel, body);
        });

        /* Text selected anywhere on the page is sent along as extra context.
           Skipped when the selection sits inside the panel itself. */
        document.addEventListener('mouseup', function (ev) {
            if (panel.contains(ev.target)) return;
            var sel = '';
            try {
                sel = window.getSelection().toString().replace(/\s+/g, ' ')
                    .replace(/^\s+|\s+$/g, '');
            } catch (e) { sel = ''; }
            if (sel.length > 4 && sel.length < 800) {
                selText = sel;
                ctxBar.innerHTML = '<strong>' +
                    escHtml(lang === 'en' ? 'Selected text: ' : '选中文本：') + '</strong> ' +
                    escHtml(sel.substring(0, 160)) + (sel.length > 160 ? '…' : '');
                ctxBar.style.display = 'block';
            }
        });

        send.addEventListener('click', sendMsg);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMsg();
            }
        });
    }

    function appendEmpty() {
        var body = document.getElementById('ebk-ai-body');
        var hint = escHtml(T('empty_hint'));
        if (docId > 0) {
            hint += '<div class="ebk-ai-gap"></div><div><strong>' +
                (lang === 'en' ? 'Loaded document: ' : '已读取单据：') +
                escHtml(docRef || ('#' + docId)) + '</strong></div>';
        }
        var div = document.createElement('div');
        div.className = 'ebk-ai-empty';
        div.innerHTML = '<div class="ebk-ai-empty-t">' + escHtml(T('empty_title')) + '</div>' +
                        '<div class="ebk-ai-empty-h">' + hint + '</div>';
        body.appendChild(div);
    }

    function scrollDown() {
        var body = document.getElementById('ebk-ai-body');
        body.scrollTop = body.scrollHeight;
    }

    function timeLabel() {
        var now = new Date();
        var pad = function (n) { return ('0' + n).slice(-2); };
        return pad(now.getHours()) + ':' + pad(now.getMinutes());
    }

    function appendMsg(role, content) {
        var body = document.getElementById('ebk-ai-body');
        var div = document.createElement('div');
        div.className = 'ebk-ai-msg ' + role;

        div.innerHTML =
            '<div class="ebk-ai-av">' + escHtml(role === 'ai' ? T('av_ai') : T('av_user')) + '</div>' +
            '<div class="ebk-ai-stack">' +
            '<div class="ebk-ai-bubble">' +
            (role === 'ai' ? renderRich(content) : escHtml(content)) +
            '</div>' +
            '<span class="ebk-ai-ts">' + timeLabel() + '</span>' +
            '</div>';

        body.appendChild(div);
        scrollDown();
    }

    function appendTyping() {
        var body = document.getElementById('ebk-ai-body');
        var div = document.createElement('div');
        div.className = 'ebk-ai-msg ai';
        div.innerHTML =
            '<div class="ebk-ai-av">' + escHtml(T('av_ai')) + '</div>' +
            '<div class="ebk-ai-typing">' +
            '<span class="ebk-ai-dot"></span>' +
            '<span class="ebk-ai-dot" style="animation-delay:.2s"></span>' +
            '<span class="ebk-ai-dot" style="animation-delay:.4s"></span>' +
            '<span class="ebk-ai-lab">' + escHtml(T('typing')) + '</span>' +
            '</div>';
        body.appendChild(div);
        scrollDown();
    }

    function removeTyping() {
        var els = document.getElementsByClassName
            ? document.getElementsByClassName('ebk-ai-typing')
            : null;
        if (els) {
            for (var i = els.length - 1; i >= 0; i--) {
                var row = els[i].parentNode;
                if (row && row.parentNode) row.parentNode.removeChild(row);
            }
        }
    }

    function sendMsg() {
        var input = document.getElementById('ebk-ai-input');
        var raw = input.value.replace(/^\s+|\s+$/g, '');
        if (!raw) return;

        input.value = '';
        input.style.height = 'auto';

        var ctxBar = document.getElementById('ebk-ai-ctx');
        ctxBar.style.display = 'none';
        /* Snapshot the selection BEFORE resetting it - the reset must not
           wipe the value we are about to send. */
        var selSnapshot = selText;
        selText = '';

        appendMsg('user', raw);

        var emptyEl = document.getElementsByClassName
            ? document.getElementsByClassName('ebk-ai-empty')[0] : null;
        if (emptyEl && emptyEl.parentNode) emptyEl.parentNode.removeChild(emptyEl);

        var sendBtn = document.getElementById('ebk-ai-send');
        sendBtn.disabled = true;
        appendTyping();

        /* doc_id / doc_type / page are sent at the TOP LEVEL of the payload:
           ajax/chat.php reads them from there. Nesting them under "context"
           (as an earlier version did) meant the server always saw an empty
           document and answered in the abstract. */
        var payload = {
            question: raw,
            context: selSnapshot,
            page: pageCtx,
            doc_id: docId,
            doc_type: docType,
            doc_ref: docRef,
            doc_url: docUrl,
            token: token
        };

        var xhr = new XMLHttpRequest();
        xhr.open('POST', endpoint, true);
        xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            removeTyping();
            var b = document.getElementById('ebk-ai-send');
            if (b) b.disabled = false;

            if (xhr.status === 200) {
                var d = null;
                try { d = JSON.parse(xhr.responseText); } catch (e) { d = null; }
                if (d && d.ok && d.answer) {
                    appendMsg('ai', d.answer);
                } else {
                    appendMsg('ai', T('err_fallback'));
                }
            } else {
                appendMsg('ai', T('err_net') + xhr.status);
            }
        };
        xhr.send(JSON.stringify(payload));
    }

    function init(cfg) {
        cfg = cfg || {};
        endpoint = cfg.endpoint || '';
        token    = cfg.token    || '';
        docId    = parseInt(cfg.doc_id, 10) || 0;
        docType  = cfg.doc_type || '';
        docRef   = cfg.doc_ref  || '';
        docUrl   = cfg.doc_url  || '';
        pageCtx  = cfg.context  || {};

        var docLang = (document.documentElement.lang || 'zh').split('-')[0].toLowerCase();
        lang = (docLang === 'en') ? 'en' : 'zh';

        buildUI();
        wire();
    }

    window.EBKAiWidget = { init: init };
})();