<?php

require_once 'query_builder.php';

# have no html output
header('Content-Type: text/plain');

require_once 'db.php';

# select db
mysql_select_db('magento');

# create object with the mangento model and the storeId
$builder = new QueryBuilder('catalog/product', array(0,1));
# load all Attributes
$builder->loadAllAttributes();

# set condetions for the select statement
$builder->setCond( 'attribute_set_id', '=' , 1); 
$builder->setCond('status','=',1);

# return the query
$query = $builder->getCountQuery();

//var_dump($query); die();

$result = mysql_query($query, $link);

if (!$result) {
    die('Invalid query: ' . mysql_error());
}

while ($row = mysql_fetch_assoc($result)) {
    echo $row['count'];
}
?>
