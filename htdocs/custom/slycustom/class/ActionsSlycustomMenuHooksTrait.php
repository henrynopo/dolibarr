<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: SLY Custom menu hooks.
 *
 * Extracted from actions_slycustom.class.php to reduce file size.
 */
trait ActionsSlycustomMenuHooksTrait
{
	/**
	 * Hook after discount split (remx 折扣拆分) — 仅当应用 sly22.0-remx-hooks.patch 后会被调用
	 *
	 * @param array            $parameters remid, newid1, newid2, socid
	 * @param DiscountAbsolute $object     被拆分的原折扣对象
	 * @param string           $action     confirm_split
	 * @return int
	 */
	public function afterSplitDiscount($parameters, &$object, &$action)
	{
		// 可在此做审计、同步等；默认无操作
		return 0;
	}

	/**
	 * Hook menuLeftMenuItems: 将 Shipment（发货）从「产品/服务」目录移到「商业」目录显示
	 *
	 * 注意：Dolibarr 仅在当前页 initHooks() 声明的 context 与 modSlyCustom 的 hooks 列表有交集时
	 * 才会加载本类。comm/index.php、product/index.php 等会 new HookManager 且只 init 单一 context
	 *（如 commercialindex、productindex），因此必须在 modSlyCustom 中声明这些 context，否则本方法
	 * 在该页根本不会执行（表现为 Commerce 首页无 Shipment、Products 首页仍显示 Shipment）。
	 *
	 * @param array $parameters ['mainmenu' => string]
	 * @param array $hook_items 左侧菜单项数组（与 Menu->liste 结构一致）
	 * @return int 0=不替换, 1=用 $this->results 替换整份菜单（由 HookManager 写入 resArray）
	 */
	public function menuLeftMenuItems($parameters, &$hook_items)
	{
		global $user, $langs, $conf;

		if (!isModEnabled('slycustom') || !isModEnabled('shipping')) {
			return 0;
		}

		$mainmenu = isset($parameters['mainmenu']) ? $parameters['mainmenu'] : '';
		// 某些版本/场景下，eldy 在 $_SESSION['mainmenu'] 为空时会把 $mainmenu 强制设为 'home'，
		// 导致首次点击 Commerce（URL 为 mainmenu=commercial）时左侧菜单按 home 渲染，Shipment 不出现。
		// 这里：1) 空时从 GET/POST 回退；2) 只要当前请求带 mainmenu=commercial 就按 commercial 处理。
		if ($mainmenu === '') {
			$mmFromRequest = GETPOST('mainmenu', 'alpha');
			if (!empty($mmFromRequest)) {
				$mainmenu = $mmFromRequest;
			}
		}
		if ($mainmenu !== 'commercial' && GETPOST('mainmenu', 'alpha') === 'commercial') {
			$mainmenu = 'commercial';
		}

		// 商业目录：在左侧菜单中追加 Shipment 块（与 core 中 products 下结构一致，mainmenu 改为 commercial）。
		// 注意：有些环境下首次点击 top menu "Commerce" 时，hook 传入的 $hook_items 可能为空数组，
		// 但我们仍然希望显示 Shipment 菜单，因此不要在 $hook_items 为空时直接 return 0。
		if ($mainmenu === 'commercial') {
			if (!is_array($hook_items)) {
				$hook_items = array();
			}
			$langs->load("sendings");
			$leftmenu = (empty($_SESSION['leftmenu']) ? '' : $_SESSION['leftmenu']);
			$usemenuhider = 1;

			$shipmentEntries = array(
				array(
					'url' => '/expedition/index.php?leftmenu=sendings',
					'titre' => $langs->trans("Shipments"),
					'level' => 0,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 500,
					'id' => '',
					'idsel' => 'sendings',
					'classname' => '',
					'prefix' => img_picto('', 'shipment', 'class="paddingright pictofixedwidth"'),
				),
				array(
					'url' => '/expedition/card.php?action=create2&amp;leftmenu=sendings',
					'titre' => $langs->trans("NewSending"),
					'level' => 1,
					'enabled' => $user->hasRight('expedition', 'creer'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 501,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				),
				array(
					'url' => '/expedition/list.php?leftmenu=sendings',
					'titre' => $langs->trans("List"),
					'level' => 1,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 502,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				),
			);
			if ($usemenuhider || empty($leftmenu) || $leftmenu == 'sendings') {
				$shipmentEntries[] = array(
					'url' => '/expedition/list.php?leftmenu=sendings&search_status=0',
					'titre' => $langs->trans("StatusSendingDraftShort"),
					'level' => 2,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 503,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				);
				$shipmentEntries[] = array(
					'url' => '/expedition/list.php?leftmenu=sendings&search_status=1',
					'titre' => $langs->trans("StatusSendingValidatedShort"),
					'level' => 2,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 504,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				);
				$shipmentEntries[] = array(
					'url' => '/expedition/list.php?leftmenu=sendings&search_status=2',
					'titre' => $langs->trans("StatusSendingProcessedShort"),
					'level' => 2,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 505,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				);
			}
			$shipmentEntries[] = array(
				'url' => '/expedition/stats/index.php?leftmenu=sendings',
				'titre' => $langs->trans("Statistics"),
				'level' => 1,
				'enabled' => $user->hasRight('expedition', 'lire'),
				'target' => '',
				'mainmenu' => 'commercial',
				'leftmenu' => 'sendings',
				'position' => 506,
				'id' => '',
				'idsel' => '',
				'classname' => '',
				'prefix' => '',
			);

			$new_menu = array_merge($hook_items, $shipmentEntries);
			$this->results = $new_menu; // HookManager 会据此设置 resArray，供 core 替换 menu_array
			return 1;
		}

		// 产品目录：从左侧菜单中移除 Shipment 块（与 core get_left_menu_products 中 expedition 一致）
		if ($mainmenu === 'products') {
			if (!is_array($hook_items)) {
				return 0;
			}
			$new_menu = array();
			foreach ($hook_items as $item) {
				$url = isset($item['url']) ? $item['url'] : '';
				$left = isset($item['leftmenu']) ? $item['leftmenu'] : '';
				$idsel = isset($item['idsel']) ? $item['idsel'] : '';
				// 与 core 一致：leftmenu/idsel 为 sendings；idsel 兼容部分菜单结构
				if (strpos($url, '/expedition/') !== false && ($left === 'sendings' || $idsel === 'sendings')) {
					continue;
				}
				$new_menu[] = $item;
			}
			if (count($new_menu) !== count($hook_items)) {
				$this->results = $new_menu;
				return 1;
			}
		}

		return 0;
	}
}

