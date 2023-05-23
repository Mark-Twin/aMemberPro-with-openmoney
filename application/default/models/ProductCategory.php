<?php
/**
 * Class represents records from table product_category
 * {autogenerated}
 * @property int $product_category_id
 * @property string $title
 * @property string $description
 * @property int $parent_id
 * @property string $code
 * @property int $sort_order
 * @see Am_Table
 */
class ProductCategory extends Am_Record
{
    protected $_childNodes = array();

    function getChildNodes()
    {
        return $this->_childNodes;
    }

    function createChildNode()
    {
        $c = new self($this->getTable());
        $c->parent_id = $this->pk();
        if (!$c->parent_id)
            throw new Am_Exception_InternalError("Could not add child node to not-saved object in ".__METHOD__);
        $this->_childNodes[] = $c;
        return $c;
    }

    public function fromRow(array $vars)
    {
        if (isset($vars['childNodes']))
        {
            foreach ($vars['childNodes'] as $row)
            {
                $r = new self($this->getTable());
                $r->fromRow($row);
                $this->_childNodes[] = $r;
            }
            unset($vars['childNodes']);
        }
        return parent::fromRow($vars);
    }

    public function save()
    {
        if ($this->pk() == $this->parent_id) {
            $this->parent_id = 0;
        }
        parent::save();
    }
}

class ProductCategoryTable extends Am_Table {
    protected $_key = 'product_category_id';
    protected $_table = '?_product_category';
    protected $_recordClass = 'ProductCategory';

    const COUNT = 'count';
    const ROOT = 'root';
    const SCOPE = 'scope';

    /**
     * Do not include categories that do not have products.
     */

    const EXCLUDE_EMPTY = 'exclude_empty';

    /**
     * Do not include hidden categories.
     */
    const EXCLUDE_HIDDEN = 'exclude_hidden';
    /**
     * array of ctegory codes to include to list
     */
    const INCLUDE_HIDDEN = 'include_hidden';

    /**
     * Do not query sub-directories
     */

    const EXCLUDE_CHILDS = 'exclude_childs';

    const ADMIN = 1;
    const USER = 2;

    protected $categoryProductsCache = null;

    /**
     * @todo protect against endless cycle (child[parent_id] <-> parent[parent_id])
     * @return ProductCategory
     */
    function getTree($cast_objects = true)
    {
        $ret = array();
        foreach ($this->_db->select("SELECT
            product_category_id AS ARRAY_KEY,
            parent_id AS PARENT_KEY, pc.*
            FROM ?_product_category AS pc
            ORDER BY 0+sort_order, title") as $r)
        {
            $ret[] = $cast_objects ? $this->createRecord($r) : $r;
        }
        return $ret;
    }
    function getUserSelectOptions($options = array())
    {
        return $this->getSelectOptions(self::USER, $options);
    }
    function getAdminSelectOptions($options = array())
    {
        return $this->getSelectOptions(self::ADMIN, $options);
    }
    function getSelectOptions($type, array $options)
    {
        $ret = array();
        $do_count = !empty($options[self::COUNT]);
        $scope = isset($options[self::SCOPE]) ? array_merge(array(-1), $options[self::SCOPE]) : null;
        $exclude_empty = !empty($options[self::EXCLUDE_EMPTY]);
        $exclude_hidden = !empty($options[self::EXCLUDE_HIDDEN]);
        $include_hidden = isset($options[self::INCLUDE_HIDDEN]) ? $options[self::INCLUDE_HIDDEN] : array();
        $root = (isset($options[self::ROOT]) && $options[self::ROOT]) ? $options[self::ROOT] : false;
        $exclude_disabled = $type == self::USER ? true : false;

        if ($do_count || $exclude_empty || $scope) {
            $cond1 = $exclude_disabled ? " AND p.is_disabled=0 " : '';
            $cond2 = $scope ? sprintf(" AND p.product_id IN (%s) ", implode(',', $scope)) : '';
            $sql = "SELECT pc.product_category_id AS ARRAY_KEY,
                pc.parent_id AS PARENT_KEY,
                pc.product_category_id, pc.title, pc.code,
                COUNT(DISTINCT p.product_id) as `count`
                FROM ?_product_category pc
                    LEFT JOIN ?_product_product_category ppc USING (product_category_id)
                    LEFT JOIN ?_product p ON p.product_id = ppc.product_id
                        AND p.is_archived=0
                        $cond1
                        $cond2
                GROUP BY pc.product_category_id
                ORDER BY parent_id, 0+pc.sort_order, pc.title";
        } else {
            $sql = "SELECT product_category_id AS ARRAY_KEY,
                parent_id AS PARENT_KEY,
                product_category_id, title, code
                FROM ?_product_category
                ORDER BY parent_id, 0+sort_order, title";
        }
        $rows = $this->_db->select($sql);

        if ($root) {
            $rows = $this->extractSubtree($rows, $root);
        }
        foreach ($rows as $id => $r){
            $this->renderNode($r, $id, $type, $options, '', $ret);
        }

        return $ret;
    }

    function getOptions()
    {
        return $this->getAdminSelectOptions();
    }

    function getSubCategoryIds($cat_id)
    {
        $res = array();
        $childNodes = $this->extractSubtree($this->getTree(false), $cat_id);
        while($node = array_shift($childNodes)) {
            array_push($res, $node['product_category_id']);
            foreach ($node['childNodes'] as $n) {
                array_push($childNodes, $n);
            }
        }
        return $res;
    }

    protected function extractSubtree($rows, $root)
    {
        $childNodes = $rows;
        while($node = array_shift($childNodes)) {
            if ($node['product_category_id'] == $root) return $node['childNodes'];
            foreach ($node['childNodes'] as $n) {
                array_push($childNodes, $n);
            }
        }
        return array();
    }

    protected function renderNode($r, $id, $type, array $options, $title, &$ret)
    {
        $do_count = !empty($options[self::COUNT]);
        $exclude_empty = !empty($options[self::EXCLUDE_EMPTY]);
        $exclude_hidden = !empty($options[self::EXCLUDE_HIDDEN]);
        $include_hidden = isset($options[self::INCLUDE_HIDDEN]) ? $options[self::INCLUDE_HIDDEN] : array();
        $exclude_disabled = self::USER ? true : false;
        $exclude_childs = !empty($options[self::EXCLUDE_CHILDS]);
        $title .= ($title ? '/' : '') . $r['title'];
        $ctitle = $title;

        if($exclude_empty && !$r['count'])
        {
            foreach($r['childNodes'] as $cid => $c) $this->renderNode($c, $cid, $type, $options, $ctitle, $ret);
            return;
        }
        if($exclude_hidden && !(empty($r['code']) || in_array($r['code'], $include_hidden)))
            //assume to do not show child if parent is hidden
            return;
        if ($do_count)
            $title .= sprintf(' (%d)', $r['count']);
        $ret [ (($type == self::ADMIN) || !$r['code'] ? $id : $r['code']) ] = $title;

        if(!$exclude_childs){
            foreach($r['childNodes'] as $cid => $c) $this->renderNode($c, $cid, $type, $options, $ctitle, $ret);
        }
    }
    /**
     * Try to find by code and by id
     * if found by id, checks that code is empty
     */
    function findByCodeThenId($codeOrId)
    {
        $r = $this->selectObjects("SELECT *
            FROM ?_product_category
            WHERE code=? OR (IFNULL(code,'')='' AND product_category_id=?)
            ORDER BY code=? DESC
            LIMIT 1", $codeOrId, $codeOrId, $codeOrId);
        if (!$r) return;
        $record = $r[0];
        if ($record->code && ($record->code != $codeOrId))
            throw new Am_Exception_InputError("Category #$record->product_category_id could not be accessed by id#");
        return $record;
    }

    function moveNodes($fromId, $toId)
    {
        $this->_db->query("UPDATE {$this->_table} SET parent_id=?d WHERE parent_id=?d",
            $toId, $fromId);
    }

    public function delete($key)
    {
        parent::delete($key);
        $this->_db->query("DELETE FROM ?_product_product_category WHERE product_category_id=?d", $key);
    }

    /**
     * @return array { category_id => [product_id1, product_id2, ...] }
     */
    public function getCategoryProducts($useCache = true)
    {
        if (!$useCache || ($this->categoryProductsCache === null))
        {
            $this->categoryProductsCache = $this->_db->selectCol("
                SELECT pc.product_category_id as ARRAY_KEY, GROUP_CONCAT(ppc.product_id)
                FROM ?_product_category AS pc LEFT JOIN ?_product_product_category AS ppc
                USING (product_category_id)
                GROUP BY pc.product_category_id");
            foreach ($this->categoryProductsCache as & $prList)
                $prList = array_filter(explode(',', $prList));
        }
        return $this->categoryProductsCache;
    }
}