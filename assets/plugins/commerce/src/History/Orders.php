<?php

namespace Commerce\History;

use Commerce\Controllers\Traits;

class Orders extends \onetableDocLister
{
    use Traits\CustomTemplatesPathTrait;

    public $table = 'commerce_orders';
    protected $extTV;

    public function __construct($modx, $cfg = [], $startTime = null)
    {
        $cfg = $this->initializeCustomTemplatesPath($cfg);
        parent::__construct($modx, $cfg, $startTime);
        $uid = (int) $this->getCFGDef('customer', $this->modx->getLoginUserID('web'));
        if (!empty ($this->_filters['where'])) {
            $this->_filters['where'] .= ' AND ';
        }
        $this->_filters['where'] .= "(`customer_id` = {$uid})";
        $this->extTV = $this->getExtender('tv', true, true);
        $this->lexicon->fromFile('order', '', $this->getCFGDef('langDir'));
        $this->lexicon->fromFile('cart', '', $this->getCFGDef('langDir'));
        $this->lexicon->fromFile('history', '', $this->getCFGDef('langDir'));
    }

    public function getDocs($tvlist = '')
    {
        $orders = parent::getDocs($tvlist);
        foreach ($orders as &$order) {
            $order['fields'] = json_decode($order['fields'], true);
            $order['status'] = ci()->statuses->getStatus($order['status_id']);
        }
        $ids = $this->cleanIDs(array_keys($orders));
        if ($ids) {
            $ids = implode(',', $ids);
            $q = $this->dbQuery("SELECT * FROM {$this->getTable('commerce_order_products')} WHERE `order_id` IN ($ids)");
            $products = [];
            while ($row = $this->modx->db->getRow($q)) {
                if ($row['product_id']) {
                    $row['options'] = json_decode($row['options'], true) ?? [];
                    $row['meta'] = json_decode($row['meta'], true) ?? [];
                    $orders[$row['order_id']]['cart']['products'][] = $row;
                } else {
                    $orders[$row['order_id']]['cart']['subtotals'][] = $row;
                }
                $products[] = $row['product_id'];
            }
        }
        if ($products) {
            $content = $this->getContentData($products);
            if ($tvlist == '') {
                $tvlist = $this->getCFGDef('tvList', '');
            }

            $this->extTV->getAllTV_Name();
            $tv = $this->extTV->getTVList($products, $tvlist);
            foreach ($orders as &$order) {
                $total = 0;
                $count = 0;
                $items_price = 0;
                foreach ($order['cart']['products'] as &$product) {
                    $product = array_merge($product, $content[$product['product_id']] ?? [],
                        $tv[$product['product_id']] ?? []);
                    $product['original_title'] = $product['pagetitle'] ?? '';
                    $product['pagetitle'] = $product['title'];
                    $product['total'] = (float) $product['price'] * $product['count'];
                    $total += $product['total'];
                    if ($product['product_id']) {
                        $count += $product['count'];
                    }
                }
                $items_price = $total;
                if(isset($order['cart']['subtotals'])){
                    foreach ($order['cart']['subtotals'] as $subtotal) {
                        $total += $subtotal['price'];
                    }
                }

                $order['cart']['count'] = $count;
                $order['cart']['total'] = $total;
                $order['cart']['items_price'] = $items_price;
            }
        }
        $this->_docs = $orders;

        return $orders;
    }

    protected function getContentData($ids)
    {
        $ids = $this->cleanIDs($ids);
        $out = [];
        if ($ids) {
            $ids = implode(',', $ids);
            $fields = $this->getCFGDef('cartFields', 'c.*');
            $q = $this->dbQuery("SELECT {$fields} FROM {$this->getTable('site_content', 'c')} WHERE `id` IN ({$ids})");
            while ($row = $this->modx->db->getRow($q)) {
                $row['product_id'] = $row['id'];
                unset($row['id']);
                $out[$row['product_id']] = $row;
            }
        }

        return $out;
    }

    public function _render($tpl = '')
    {
        $out = '';
        $separator = $this->getCFGDef('outputSeparator', '');
        if ($tpl == '') {
            $tpl = $this->getCFGDef('tpl', '');
        }
        if ($tpl != '') {
            $this->toPlaceholders(count($this->_docs), 1, "display"); // [+display+] - сколько показано на странице.
            $i = 1;
            $sysPlh = $this->renameKeyArr($this->_plh, $this->getCFGDef("sysKey", "dl"));
            $noneTPL = $this->getCFGDef("noneTPL", "");
            if (count($this->_docs) == 0 && $noneTPL != '') {
                $out = $this->parseChunk($noneTPL, $sysPlh);
            } else {
                /**
                 * @var $extSummary summary_DL_Extender
                 */
                $extSummary = $this->getExtender('summary');

                /**
                 * @var $extPrepare prepare_DL_Extender
                 */
                $extPrepare = $this->getExtender('prepare', true);

                $this->skippedDocs = 0;
                foreach ($this->_docs as $item) {
                    $this->renderTPL = $tpl;

                    $item[$this->getCFGDef("sysKey", "dl") . '.summary'] = $extSummary ? $this->getSummary(
                        $item,
                        $extSummary
                    ) : '';

                    $item = array_merge(
                        $item,
                        $sysPlh
                    ); //inside the chunks available all placeholders set via $modx->toPlaceholders with prefix id, and with prefix sysKey
                    $item[$this->getCFGDef(
                        "sysKey",
                        "dl"
                    ) . '.iteration'] = $i; //[+iteration+] - Number element. Starting from zero

                    $date = $this->getCFGDef('dateSource', 'created_at');
                    if (isset($item[$date])) {
                        $_date = is_numeric($item[$date]) && $item[$date] == (int) $item[$date] ? $item[$date] : strtotime($item[$date]);
                        if ($_date !== false) {
                            $_date = $_date + $this->modx->config['server_offset_time'];
                            $dateFormat = $this->getCFGDef('dateFormat', 'd.m.Y H:i');
                            if ($dateFormat) {
                                $item['date'] = date($dateFormat, $_date);
                            }
                        }
                    }

                    $findTpl = $this->renderTPL;
                    $tmp = $this->uniformPrepare($item, $i);
                    extract($tmp, EXTR_SKIP);
                    if ($this->renderTPL == '') {
                        $this->renderTPL = $findTpl;
                    }

                    if ($extPrepare) {
                        $item = $extPrepare->init($this, [
                            'data'      => $item,
                            'nameParam' => 'prepare'
                        ]);
                        if ($item === false) {
                            $this->skippedDocs++;
                            continue;
                        }
                    }
                    $item['cart']['products'] = $this->renderCart($item['cart']);
                    $tmp = $this->parseChunk($this->renderTPL, $item);
                    if ($this->getCFGDef('contentPlaceholder', 0) !== 0) {
                        $this->toPlaceholders(
                            $tmp,
                            1,
                            "item[" . $i . "]"
                        ); // [+item[x]+] – individual placeholder for each iteration documents on this page
                    }
                    $out .= $tmp;
                    if (next($this->_docs) !== false) {
                        $out .= $separator;
                    }
                    $i++;
                }
            }
            $out = $this->renderWrap($out);
        }

        return $this->toPlaceholders($out);
    }

    protected function renderCart($cart)
    {
        $out = $wrap = $subtotals = '';
        $wrapTpl = $this->getCFGDef('cartTpl');
        $rowTpl = $this->getCFGDef('cartRowTpl');
        $optionsTpl = $this->getCFGDef('optionsTpl');
        $subtotalsTpl = $this->getCFGDef('subtotalsTpl');
        $subtotalsRowTpl = $this->getCFGDef('subtotalsRowTpl');
        $extPrepare = $this->getExtender('prepare');
        foreach ($cart['products'] as $item) {
            if ($extPrepare) {
                $item = $extPrepare->init($this, [
                    'data'      => $item,
                    'nameParam' => 'prepareCartRow'
                ]);
                if ($item === false) {
                    $this->skippedDocs++;
                    continue;
                }
            }
            $options = '';
            if (isset($item['options']) && is_array($item['options'])) {
                foreach ($item['options'] as $key => $option) {
                    $options .= $this->parseChunk($optionsTpl, [
                        'key'    => htmlentities($key),
                        'option' => nl2br(htmlentities(is_scalar($option) ? $option : json_encode($option,
                            JSON_UNESCAPED_UNICODE))),
                    ]);
                }
            }
            $item['options'] = $options;
            $wrap .= $this->parseChunk($rowTpl, $item);
        }

        $subtotals = '';
        if(isset($cart['subtotals'])){
            foreach ($cart['subtotals'] as $item) {
                if ($extPrepare) {
                    $item = $extPrepare->init($this, [
                        'data'      => $item,
                        'nameParam' => 'prepareSubtotalsRow'
                    ]);
                    if ($item === false) {
                        $this->skippedDocs++;
                        continue;
                    }
                }
                $subtotals .= $this->parseChunk($subtotalsRowTpl, $item);
            }
            $subtotals = $this->parseChunk($subtotalsTpl, ['wrap' => $subtotals]);
        }
        if (!empty($wrap)) {
            $out = $this->parseChunk($wrapTpl, [
                'dl.wrap'     => $wrap,
                'subtotals'   => $subtotals,
                'total'       => $cart['total'],
                'count'       => $cart['count'],
                'items_price' => $cart['items_price'],
            ]);
        }

        return $out;
    }
}
