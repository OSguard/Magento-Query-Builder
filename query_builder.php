<?php

/**
  * Mangento Query Builder for generating easy sql-select-statements
  *
  *
  * Copyright 2011 initOS GmbH & Co. KG, Markus Schneider <markus.schneider-at-initos.com>. All rights reserved.
  *  
  * Redistribution and use in source and binary forms, with or without modification, are
  * permitted provided that the following conditions are met:
  * 
  *    1. Redistributions of source code must retain the above copyright notice, this list of
  *       conditions and the following disclaimer.
  * 
  *    2. Redistributions in binary form must reproduce the above copyright notice, this list
  *       of conditions and the following disclaimer in the documentation and/or other materials
  *       provided with the distribution.
  * 
  * THIS SOFTWARE IS PROVIDED BY initOS GmbH & Co. KG ''AS IS'' AND ANY EXPRESS OR IMPLIED
  * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
  * FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL initOS GmbH & Co. KG OR
  * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
  * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
  * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
  * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
  * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
  * ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
  * 
  * The views and conclusions contained in the software and documentation are those of the
  * authors and should not be interpreted as representing official policies, either expressed
  * or implied, of initOS GmbH & Co. KG.
  *
  */

class QueryBuilder{

	/**
	  * name of entity model
	  * Example: catalog/product
	  */
	protected $entity_model;

	/**
	  * array of all store ids
	  */
	protected $storeIDs;

	/**
	  * array of all to selected attributes
	  */
	protected $attributes;

	/**
	  * list of all avalible attributes
	  */
	protected $attribute_list;

	/**
	  * list of all select condetions
	  */
	protected $cond_list;

	/**
	  * basic consturctor
	  *
	  * @param String $entityModel name of entity model
	  * @param int/Array $store storeId(s) for shop you want to work with
	  */
	public function __construct($entityModel, $store = 0){
		$this->entity_model = $entityModel;
		if( is_array($store) )
			$this->storeIds = $store;
		elseif( is_int($store) )
			$this->storeIds = array( $store );
		else
			$this->storeIds = array();
		$this->attributes = array();
		$this->attribute_list = array();
		$this->cond_list = array();
	}


	/**
	  * select a attribute for the Select statement
	  *
	  * @param mixed $attr Array or String
	  */
	public function selectAttributes($attr){
		// if array just append everyone ones		
		if( is_array($attr) ){
			foreach( $attr as $a ){
				$this->selectAttributes( $a );
			}
			return true;
		}
		// dont want to have multible entrys
		if( in_array($attr, $this->attributes) ){
			return true;
		}
		// appent to List
		$this->attributes[] = $attr;
	}

	/**
	  * load Metadatas for all avalible Attributes
	  */
	public function loadAllAttributes(){
		// build query with the eav_attributs values		
		$sql = "SELECT *, ea.attribute_id as attribute_id FROM `eav_attribute` ea
			left join `eav_attribute_option` eo on ea.attribute_id = eo.attribute_id
			left join `eav_attribute_option_value` ev on eo.option_id = ev.option_id \n";

		if( !empty($this->storeIds) )
			$sql .=	" and ev.store_id IN (".implode(',',$this->storeIds).") \n";

		$sql .=	" WHERE ea.`entity_type_id` = ( SELECT entity_type_id
			FROM `eav_entity_type` WHERE entity_model LIKE \"".$this->entity_model."\" )";
		//var_dump( $sql );
		$result = mysql_query( $sql );
		if( $result === false )
			throw new Exception('can not load Attributes from Database');

		// store all needed metadata
		while ($row = mysql_fetch_assoc($result)) {
			if( !array_key_exists( $row['attribute_code'] , $this->attribute_list ) ){
				$this->attribute_list[$row['attribute_code']] = array();
				$this->attribute_list[$row['attribute_code']]['attribute_id'] = $row['attribute_id'];
				$this->attribute_list[$row['attribute_code']]['entity_type_id'] = $row['entity_type_id'];
				$this->attribute_list[$row['attribute_code']]['attribute_model'] = $row['attribute_model'];
				$this->attribute_list[$row['attribute_code']]['backend_model'] = $row['backend_model'];
				$this->attribute_list[$row['attribute_code']]['backend_type'] = $row['backend_type'];
				$this->attribute_list[$row['attribute_code']]['is_required'] = $row['is_required'];
				if( !empty($row['value']) ){
					$this->attribute_list[$row['attribute_code']]['values'] = array( $row['option_id'] => $row['value']  );
					$this->attribute_list[$row['attribute_code']]['type'] = 'option';
				}else{
					$this->attribute_list[$row['attribute_code']]['type'] = 'simple';
				}
			}else{ 
			    if( !empty($row['value']) ){
				    $this->attribute_list[$row['attribute_code']]['values'][$row['option_id']] = $row['value'];
			    }
			}
		}

		// load all fields from main table
	 	$result = mysql_query( "EXPLAIN ". str_replace('/','_', $this->entity_model) ."_entity" );
		if( $result === false )
			throw new Exception('can not load Fields from table '.str_replace('/','_', $this->entity_model) ."_entity" );

		//  override all fields in the metadata storage
		while ($row = mysql_fetch_assoc($result)) {
			$this->attribute_list[$row['Field']] = array( 's' => 'a.'.$row['Field'] );			
			/*if( !array_key_exists( $row['Field'] , $this->attribute_list ) ){
				
			}else{
				var_dump( $this->attribute_list[$row['Field']] );
				throw new Exception('Attribute conflict with '.$row['Field']);
			}*/
		}

		// manual set attribute_set select
		$this->attribute_list["attribute_set"] = array('s' => 'b.attribute_set_name');

	}

	/**
	  * can manual overload for select a attribute
	  */
	public function setAttrQuery( $a , $query ){
		$this->attribute_list[$a] = array('sub' => $query);
	}

	/**
	  * return list off all avaleble attributes
	  */
	public function getAttrNames(){
		if( empty($this->attribute_list) )
			$this->loadAllAttributes();
		return array_keys( $this->attribute_list );
	}

	/**
	 * returns the select part of SQL-Statment
	 */
	private function __buildSelect(&$join){
	    $select = "";
	    $i = 0;

        foreach( $this->attributes as $a ){

            if( !array_key_exists( $a , $this->attribute_list ) )
                continue;

            $opt = $this->attribute_list[$a];
            
            // insert main table to select
            if( array_key_exists( 's' , $opt)){
                $select .= " ". $opt['s'] . " as " .$a. ",\n";
                continue;
            }       
            // insert a subselect
            if( array_key_exists( 'sub', $opt)){
                $select .= " (". $opt['sub'] . " ) as " .$a. ",\n";
                continue;
            }
            // insert simple eav attribute type
            if( $opt['type'] == 'simple' && $i < 64 ){
                $select .= " t".$i . ".value as ". $a . " ,\n";
                $join   .= "left join 
                        ". str_replace('/','_', $this->entity_model) ."_entity_". $opt['backend_type'] ." t".$i."
                        on 
                        t".$i.".attribute_id= ".$opt['attribute_id']." and
                        t".$i.".entity_id=a.entity_id and
                        t".$i.".store_id = 0 \n";
                $i++;
            }
            // insert simple eav attribute over subselect if max joins are reached
            if( $opt['type'] == 'simple' && $i >= 64 ){
                $select .= " (select
                            entity.value ".$a."
                        from
                            ". str_replace('/','_', $this->entity_model) ."_entity_". $opt['backend_type'] ." entity
                        where
                            entity.entity_id=a.entity_id and
                            entity.attribute_id= ".$opt['attribute_id']." and
                            entity.store_id IN (".implode(',',$this->storeIds).") 
                        order by val.store_id desc
                        limit 1 ) as ". $a . " ,\n";
        
            }
            // insert option eav attribute to select
            if( $opt['type'] == 'option' ){
                $select .= " ( select
                            val.value ".$a."
                        from
                            catalog_product_entity_int entity,
                            eav_attribute_option_value val,
                            eav_attribute_option opt
                        where
                            entity.entity_id=a.entity_id and
                            entity.attribute_id=".$opt['attribute_id']." and
                            entity.store_id = 0 and
                            entity.value=val.option_id and
                            opt.attribute_id=entity.attribute_id and
                            opt.option_id=val.option_id and
                            val.store_id IN (".implode(',',$this->storeIds).") 
                        order by val.store_id desc
                        limit 1  ) as " .$a. ",\n";
            }

        }
	    
	    // remove last comma
	    return trim( $select ," ,\n" );
	}

     /**
      * set select condition
      *
      * @param string $attr attribute for the condition
      * @param string $bed operator( in , >=, ==, !=, <=, LIKE, ILIKE )
      * @param mixed $val Value or Subselect to compare with
      */
    public function setCond( $attr, $bed, $val){
        $this->cond_list[] = array( 'attr' => $attr, 'bed' => $bed, 'val' => $val );
    }
	
	/**
	 * get conditions for the select statment
	 * @param string $a_join
	 * @param array $a_where
	 */
	private function __getWhere(&$a_join, &$a_where){
	    $c = 0;
        foreach( $this->cond_list as $cond ){
            if( !array_key_exists( $cond['attr'] , $this->attribute_list ) )
                continue;
            
            $opt = $this->attribute_list[$cond['attr']];
            if( $cond['bed'] == 'in' && array_key_exists( 's' , $opt)){
                $a_where[] = "entity.".$cond['attr']." IN (".$cond['val'].")";
                continue;
            }
            if(array_key_exists( 's' , $opt)){
                $a_where[] = "entity.".$cond['attr']." ".$cond['bed']." ".$cond['val'];
                continue;
            }
            if( $cond['bed'] == 'in'){
                $a_join .= "left join 
                            ". str_replace('/','_', $this->entity_model) ."_entity_". $opt['backend_type'] ." c".$c."
                        on
                            c".$c.".entity_id=entity.entity_id and
                            c".$c.".attribute_id=".$opt['attribute_id']." and
                            c".$c.".store_id=0\n";
                $a_where[] = "c".$c.".value IN (".$cond['val'].")";
                $c++;
                continue;
            }
            if( $cond['bed'] == 'LIKE' || $cond['bed'] == 'ILIKE'){
                $a_join .= "left join 
                            ". str_replace('/','_', $this->entity_model) ."_entity_". $opt['backend_type'] ." c".$c."
                        on
                            c".$c.".entity_id=entity.entity_id and
                            c".$c.".attribute_id=".$opt['attribute_id']." and
                            c".$c.".store_id=0\n";
                $a_where[] = "c".$c.".value ".$cond['bed']." %".$cond['val']."%";
                $c++;
                continue;
            }
            $a_join .= "left join 
                        ". str_replace('/','_', $this->entity_model) ."_entity_". $opt['backend_type'] ." c".$c."
                    on
                        c".$c.".entity_id=entity.entity_id and
                        c".$c.".attribute_id=".$opt['attribute_id']." and
                        c".$c.".store_id=0\n";
            if( $cond['val'] === 0 && $cond['bed'] == '=' && $opt['is_required'] == 0){
                $a_where[] = "( c".$c.".value is null or c".$c.".value = 0 )";
            }
            else{
                if( !is_int($cond['val']) && !empty($this->attribute_list[$cond['attr']]['values']) ){
                    $values = array_flip($this->attribute_list[$cond['attr']]['values']);
                    if( !empty($values[$cond['val']]) ){
                        $cond['val'] = $values[$cond['val']];
                    }
                }
                
                
                $a_where[] = "c".$c.".value ".$cond['bed']." ".$cond['val'];
            }
            $c++;
        }
	}
	
	/**
	  * build query and returns the query-string
	  */
	public function getQuery(){

		if( empty($this->attribute_list) )
			$this->loadAllAttributes();

		$join = "";
		$select = $this->__buildSelect($join);
		
		$a_join = "";
		$a_where = array();
		$this->__getWhere($a_join, $a_where);

		$query = "select " . $select . "
				from
				    (
					select
					    entity.*
					from 
					    ". str_replace('/','_', $this->entity_model) ."_entity entity \n";
		$query .= $a_join . " \n";

		if( !empty($a_where) ){
			$query .= "WHERE ".implode("\n AND ", $a_where);
		}
		

		$query .= "   ) a
		
		    left join
			eav_attribute_set b
		    on
			b.attribute_set_id=a.attribute_set_id
		    " . $join;    

		return $query;

	}

    /**
      * build query for count entity and returns the query-string
      */
    public function getCountQuery(){

        if( empty($this->attribute_list) )
            $this->loadAllAttributes();
        
        $a_join = "";
        $a_where = array();
        $this->__getWhere($a_join, $a_where);

        $query = "select
                        count(*) as count
                    from 
                        ". str_replace('/','_', $this->entity_model) ."_entity entity \n";
        $query .= $a_join . " \n";

        if( !empty($a_where) ){
            $query .= "WHERE ".implode("\n AND ", $a_where);
        }

        return $query;

    }
	
}



?>
