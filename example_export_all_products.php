<?php

require_once 'query_builder.php';

# have no html output
header('Content-Type: text/plain');

require_once 'db.php';

# select db
mysql_select_db('magento');

# create object with the mangento model and the storeId
$builder = new QueryBuilder('catalog/product', array(0,1));

# load all attributes
$builder->loadAllAttributes();

# select attributes
$attr = array(
    "entity_id",
    "sku",
    "name",
    "price",
    "url_path",
    "url_key",
    "attribute_set",
    "category_ids",
    "status",
    "created_at",
    "updated_at"
    );


$builder->selectAttributes( $attr );

# manual override for a select attribute
$builder->setAttrQuery( "category_ids", "select 
					    group_concat(category_id separator \",\")
					from
					    catalog_category_product_index cat
					where
					    cat.product_id=a.entity_id and
					    cat.store_id=1
					group by cat.product_id");


# set condetions for the select statement
//$builder->setCond( 'type_id', '=', '"downloadable"');

# return the query
$query = $builder->getQuery();

//var_dump($query); die();

$result = mysql_query($query, $link);

if (!$result) {
    die('Invalid query: ' . mysql_error());
}

echo '"'.implode('";"',$attr)."\"\n";

while ($row = mysql_fetch_assoc($result)) { 
    echo '"'.implode('";"',$row).'"';
    echo "\n";
}

?>
