//<?php
/**
 * History
 *
 * Orders history
 *
 * @category    snippet
 * @version     1.0.0
 * @author      Pathologic
 * @internal    @modx_category Commerce
 * @internal    @installset base
*/

if (defined('COMMERCE_INITIALIZED')) {
    $theme = $theme ?? 'history/';

    return $modx->runSnippet('DocLister', array_merge([
	'controller'      => '\Commerce\History\Orders',
	'tpl'             => '@FILE:' . $theme . 'order',
	'cartTpl'         => '@FILE:' . $theme . 'cart_wrap',
	'cartRowTpl'      => '@FILE:' . $theme . 'cart_row',
	'optionsTpl'      => '@FILE:' . $theme . 'cart_row_options_row',
	'ownerTPL'        => '@FILE:' . $theme . 'orders_wrap',
	'noneTPL'         => '@FILE:' . $theme . 'orders_empty',
	'subtotalsTpl'    => '@FILE:' . $theme . 'cart_subtotals',
	'subtotalsRowTpl' => '@FILE:' . $theme . 'cart_subtotals_row',
	'langDir'		  => 'assets/plugins/commerce/lang/',
	'noneWrapOuter'   => 0,
    ], $params, [
	'idType'      => 'documents',
	'ignoreEmpty' => 1,
    ]));
}
