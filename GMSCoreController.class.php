<?php

/**
 * Author:
 *  - Captank (RK2)
 *
 * @Instance
 *
 */
class GlobalShopCoreController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;

	/** @Inject */
	public $chatBot;
	
	/** @Inject */
	public $db;
	
	/** @Inject */
	public $text;
	
	/** @Inject */
	public $util;
	
	/**
	 * @Setup
	 */
	public function setup() {
		$this->db->loadSQLFile($this->moduleName, "gms");
//		var_dump($this->getShop(1), $this->getShop("Captank"), $this->getShop("Potato"), $this->getShop("xD"));
//		var_dump($this->itemSearch(Array('pant')), $this->itemSearch(Array('pant'),50,300), $this->itemSearch(Array('pant'), false, false, 105));
//		$shop = $this->getShop(1, false, false);
//		var_dump($this->getShopItems($shop->id, 1));
		var_dump($this->formatCategory($this->getShop(1),1));
	}
	
	/**
	 * Get shop data.
	 *
	 * @param mixed $identifier - either the owner/contact name or the shop id
	 * @param boolean $contacts - defines if contacts will be fetched, default true
	 * @param boolean $items - defines if items will be fetched, default true
	 * @return array - the structured array with shop data, if no shop for $identifier was found NULL
	 */
	public function getShop($identifier, $contacts = true, $items = true) {
		if(preg_match("~^\d+$~",$identifier)) {
			$sql = <<<EOD
SELECT
	`gms_shops`.`id`,
	`gms_shops`.`owner`
FROM
	`gms_shops`
WHERE
	`gms_shops`.`id` = ?
LIMIT 1
EOD;
			$shop = $this->db->query($sql, $identifier);
			if(count($shop) != 1) {
				return null;
			}
		}
		else {
			$sql = <<<EOD
SELECT DISTINCT
	`gms_shops`.`id`,
	`gms_shops`.`owner`
FROM
	`gms_shops`,
	`gms_contacts`
WHERE
	(`gms_shops`.`owner` = ?  OR `gms_contacts`.`character` = ? ) AND `gms_shops`.`id` = `gms_contacts`.`shopid`
LIMIT 1
EOD;
			$shop = $this->db->query($sql, $identifier, $identifier);
			if(count($shop) != 1) {
				return null;
			}
		}
		$shop = $shop[0];

		if($contacts) {
			$shop->contacts = $this->getShopContacts($shop->id);
		}
		else {
			$shop->contacts = null;
		}
		if($items) {
			$shop->items = $this->getShopItems($shop->id);
		}
		else {
			$shop->items = null;
		}
		return $shop;
	}
	
	/**
	 * Get items data for a shop
	 *
	 * @param int $shopid - the shop id
	 * @param mixed $category - int for the category id, false for all categories
	 * @return array - array of DBRows for the item data
	 */
	public function getShopItems($shopid, $category = false) {
		$data = Array($shopid);
		
		if($category !== false) {
			$sql = " AND `gms_item_categories`.`category` = ?";
			$data[] = $category;
		}
		else {
			$sql = '';
		}
		$sql = <<<EOD
SELECT
	`gms_items`.`id`,
	`gms_items`.`lowid`,
	`gms_items`.`highid`,
	`gms_items`.`ql`,
	`aodb`.`name`,
	`aodb`.`icon`,
	`gms_items`.`price`,
	`gms_item_categories`.`category`
FROM
	`gms_items`
		LEFT JOIN
	`aodb` ON `gms_items`.`lowid` = `aodb`.`lowid` AND `gms_items`.`highid` = `aodb`.`highid`
		LEFT JOIN
	`gms_item_categories` ON `gms_item_categories`.`lowid` = `gms_items`.`lowid` AND `gms_item_categories`.`highid` = `gms_items`.`highid`
WHERE
	`gms_items`.`shopid` = ?$sql
ORDER BY
	`gms_item_categories`.`category` ASC, `aodb`.`name` ASC, `gms_items`.`ql` ASC, `gms_items`.`price` ASC
EOD;
		return $this->db->query($sql, $data);
	}
	
	/**
	 * Get contact characters for an shop
	 *
	 * @param int $shopid - the shop id
	 * @return array - array of DBRows for the contact data
	 */
	public function getShopContacts($shopid) {
		$sql = <<<EOD
SELECT
	`gms_contacts`.`character`
FROM
	`gms_contacts`
WHERE
	`gms_contacts`.`shopid` = ?
ORDER BY
	`gms_contacts`.`character` ASC
EOD;
		return $this->db->query($sql, $shopid);
	}
	
	/**
	 * Search for an item.
	 *
	 * @param array $keywords - array of keywords
	 * @param mixed $minQL - int for min ql, false for inactive
	 * @param mixed $maxQL - int for max ql, false for inactive
	 * @param mixed $exactQL - int for for exact ql, false for inactive
	 * @return array - array of DBRow for found items, null if no valid keywords
	 */
	public function itemSearch($keywords, $minQL = false, $maxQL = false, $exactQL = false) {
		$data = Array();
		$sqlPattern = Array();
		foreach($keywords as $keyword) {
			if(strlen($keyword) > 2) {
				$data[] = "%$keyword%";
				$sqlPattern[] = "`aodb`.`name` LIKE ?";
			}
		}
		
		if(count($data) == 0) {
			return null;
		}

		if($minQL !== false && $maxQL !== false) {
			$data[] = $minQL;
			$data[] = $maxQL;
			$sqlPattern[] = "`gms_items`.`ql` >= ?";
			$sqlPattern[] = "`gms_items`.`ql` <= ?";
		}
		elseif($exactQL !== false) {
			$data[] = $exactQL;
			$sqlPattern[] = "`gms_items`.`ql` = ?";
		}
		$sql = implode(" AND ", $sqlPattern);
		$sql = <<<EOD
SELECT
	`gms_items`.`id`,
	`gms_items`.`shopid`,
	`gms_items`.`lowid`,
	`gms_items`.`highid`,
	`gms_items`.`ql`,
	`gms_items`.`price`,
	`aodb`.`icon`,
	`aodb`.`name`,
	`gms_item_categories`.`category`
FROM
	`gms_items`
		LEFT JOIN
    `aodb` ON `gms_items`.`lowid` = `aodb`.`lowid` AND `gms_items`.`highid` = `aodb`.`highid`
		LEFT JOIN
	`gms_item_categories` ON `gms_items`.`lowid` = `gms_item_categories`.`lowid` AND `gms_items`.`highid` = `gms_item_categories`.`highid`
WHERE
	$sql
ORDER BY
	`gms_item_categories`.`category` ASC, `aodb`.`name` ASC, `gms_items`.`ql` ASC, `gms_items`.`price` ASC
LIMIT 40
EOD;
		return $this->db->query($sql, $data);
	}
	
	/**
	 * Get all categories.
	 *
	 * @return array - returns an array of categories, array index is category id and array value is category name
	 */
	public function getCategories() {
		$sql = <<<EOD
SELECT
	`gms_categories`.`id`,
	`gms_categories`.`name`
FROM
	`gms_categories`
EOD;
		$data = $this->db->query($sql);
		
		$result = Array();
		foreach($data as $category) {
			$result[$category->id] = $category->name;
		}
		return $result;
	}
	
	/**
	 * Format shop for messages.
	 *
	 * @params array $shop - the shop array structur
	 * @return string - the formated message blob
	 */
	public function formatShop($shop) {
		$categories = $this->getCategories();
		
		$cats = Array();
		foreach($shop->items as $item) {
			if(isset($cats[$item->category])) {
				$cats[$item->category]++;
			}
			else {
				$cats[$item->category] = 1;
			}
		}
		
		if(count($cats) == 0) {
			$cats[] = '<tab>This shop is empty at the moment.';
		}
		else {
			foreach($cats as $cid => &$cat) {
				$cat = sprintf('<tab>%s (%d %s)', $categories[$cid], $cat, ($cat > 1 ? 'items' : 'item'));
			}
		}
		return $this->text->make_blob($this->getTitle($shop), implode('<br><br>',$cats));
	}
	
	/**
	 * Format shop category for messages.
	 *
	 * @params array $shop - the shop array structur
	 * @params int $category - the category id
	 * @return string - the formated message blob
	 */
	public function formatCategory($shop, $category) {
		$categories = $this->getCategories();
		if(!isset($categories[$category])) {
			return "Error! Invalid id '$category'";
		}
		$items = Array(); //$item["lowid/highid"] = array (ql => item)
		foreach($shop->items as $item) {
			if($item->category == $category) {
				$idx = $item->lowid.'/'.$item->highid;
				if(!isset($items[$idx])) {
					$items[$idx] = Array($item->ql => $item);
				}
				else {
					$items[$idx][$item->ql] = $item;
				}
			}
		}
		
		$out = Array();
		foreach($items as $item) {
			$tmp = Array();
			foreach($item as $ql => $obj) {
				$tmp[] = '['.$this->text->make_item($obj->lowid, $obj->highid, $ql, "QL$ql").' '.$this->priceToString($obj->price).']';
			}
			$out[] = sprintf("<tab>%s %s<br><tab>%s", $this->text->make_image($obj->icon), $obj->name, implode(' ', $tmp));
		}
		$out = implode('<br><br><pagebreak>', $out);
		return $this->text->make_blob($this->getTitle($shop).' - '.$categories[$category], $out);
	}
	
	/**
	 * Generates the contact chunk for messages.
	 *
	 * @param array $shop - the shop array structur
	 * @return string - the formated string chunk
	 */
	public function formatContacts($shop) {
		return '<center>contacts</center>';
	}
	
	public function getTitle($shop) {
		if($this->util->endsWith($shop->owner, 's')) {
			return $shop->owner."' shop";
		}
		else {
			return $shop->owner.'s shop';
		}
	}
	
	/**
	 * Parses a price string to its integer value.
	 *
	 * @param string $price - the price string
	 * @retrun int - returns the integer value of the price, 0 if it is an offer, -1 if the price string is invalid.
	 */
	public function parsePrice($price) {
		$price = strtolower($price);
		if($price == 'offer') {
			return 0;
		}
		elseif(preg_match("~^\\d+$~",$price)) {
			$price = intval($price);
		}
		if(preg_match("~^(\\d*\\.?\\d+)(b|m|k)$~",$price,$match)) {
			$price = floatval('0'.$match[1]);
			switch($match[2]) {
				case 'b':
						$price *= 1000000000.0;
					break;
				case 'm':
						$price *= 1000000.0;
					break;
				case 'k':
						$price *= 1000.0;
					break;
			}
			$price = ceil($price);
		}
		else {
			return -1;
		}
		return $price;
	}
	
	/**
	 * Converts a price to its string.
	 *
	 * @param int $price - the price
	 * return string the string of the price
	 */
	public function priceToString($price) {
		if($price == 0) {
			return 'offer';
		}
		elseif($price < 1000) {
			return $price;
		}
		elseif($price < 1000000) {
			return ($price/1000.0).'k';
		}
		elseif($price < 1000000000) {
			return ($price/1000000.0).'m';
		}
		else {
			return ($price/1000000000.0).'b';
		}
	}
}
