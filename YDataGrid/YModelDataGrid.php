<?php
class YModelDataGrid extends YDataGrid {
	/**
	 * 分列
	 * @param string $model_name 模型名称
	 * @param unknown_type $col entity_id所在的列名，此列内的值必须是某个对象的ID
	 * @param unknown_type $conf 分列规则(以StoreGoods为例)：array('goods_name', 'gid' => '$data>1:$data:"--"', 'barcode', ...)
	 */
	public function parse($model_name, $col, $conf){
		if(empty($this->data))return $this;
		$col = $this->getColumnNum($col);
		$col_index = $col + $this->keys_count - 1;
		for($row_index = $this->title_row, $c = count($this->data); $row_index < $c; ++$row_index){
			$entity_id = $this->data[$row_index][$col_index]['v'];
			$model = new $model_name;
			$entity = $model->findByPk($entity_id);
			if(empty($entity)){
				$entity = $model;
			}
			$keys = $this->getRowKeys($row_index);
			if($keys!==null){
				$key = $keys[0];
			}
			$i = 0;
			foreach ($conf as $field => $val){
				if(is_numeric($field)){
					//直接指定列名
					$field = $val;
					$val = 'php:$data';
				}
				$fields = explode('.', $field);
				$d = $entity;
				for($j = 0, $fc = count($fields); $j < $fc; ++$j){
					$f = $fields[$j];
					if($j < $fc - 1){
						$d = $d->$f;
						if(empty($d)){
							$data = '';
							break;
						}
					}
				}

				if(!empty($d)){
					$data = $d->$f;
				}
				if($row_index == $this->title_row && $this->title_row > 0){
					//添加标题
					if(empty($d)){
						$column_title = '--';
					}else{
						$column_title = $d->getAttributeLabel($f);
					}

					if($i == 0){
						$this->data[0][$col - 1] = array(
							'v' => $column_title,
							't' => self::$TYPE_TITLE,
						);
					}else{
						$this->data[0] = $this->_insertArrayValue($this->data[0], array(
							'v' => $column_title,
							't' => self::$TYPE_TITLE,
						), $col + $i - 1);
					}
				}
				
				if($val instanceof YDataGridCell){
					//支持动态类执行
					$keys = $this->getRowKeys($row_index);
					if($keys!==null){
						$val->key = $keys[0];
						$val->keys = $keys;
					}
					if($val->grid === null){
						$val->grid = &$this;
					}
					$val->row_index = $row_index;
					$val->col_index = $col_index;
					$val->cell_data = $data;
					$val = $val->toString();
				}elseif(strpos($val, 'php:') === 0){
					$val = substr($val, 4);
					@eval("\$val = $val;");
				}elseif(strpos($val, 'exp:') === 0){
					$val = substr($val, 4);
					//执行表达式
					$val = $this->_exec(null, null, $val, $data);
				}
				$new_data = array(
					't' => self::$TYPE_STRING,
					'v' => $val,
				);
				if($i == 0){
					$this->data[$row_index][$col_index] = $new_data;
				}else{
					$this->data[$row_index] = $this->_insertArrayValue($this->data[$row_index], $new_data, $col_index + $i);
				}
				++$i;
			}
		}
		return $this;
	}
	
	/**
	 * 缓存
	 * @var unknown
	 */
	private $model_data_cache = null;
	
	/**
	 * 
	 * @param unknown $model_name
	 * @param unknown $col
	 * @param unknown $conf
	 * @return YModelDataGrid
	 */
	public function parseMongo($model_name, $col, $conf, $condition = array()){
		if(empty($this->data))return $this;
		if($this->model_data_cache == null){
			if(is_string($model_name)){
				$model = new $model_name;
			}else if($model_name instanceof MongoModel){
				$model = $model_name;
			}else{
				throw new Exception('无效Mondel!');
			}
			$fs = array();
			foreach ($conf as $field => $val){
				if(is_int($field)){
					//直接指定列名
					$fs[$val] = true;
				}else{
					$fs[$field] = true;
				}
			}
			if(empty($condition)){
				$col_values = $this->getColumnData($col, $model);
				$condition['_id'] = array(
					'$in' => $col_values
				);
			}
			$this->model_data_cache = $model->find($condition, $fs);
		}
		
		$col = $this->getColumnNum($col);
		$this->_parse_col_val($conf, $col);
		return $this;
	}

	/**
	 * 配置总库Goods信息，$col使用的是goods._id
	 * @param string $col
	 * @return YModelDataGrid
	 */
	public function quickParseGoodsMongo($col = 'A'){
		$col_index = $this->getColumnNum($col) + $this->keys_count - 1;
		return $this->parseMongo('GoodsModel', $col, array(
			'barcode',
			'name',
		))
		->setColTitle($col_index + 1, '商品条码')
		->setColTitle($col_index + 2, '商品名称');
	}
	
	/**
	 * 配置总库Goods信息，$col使用的是goods_spec_id
	 * @param string $col
	 * @return YModelDataGrid
	 */
	public function quickParseGoodsSpecMongo($col = 'A'){
		$model = new GoodsModel;
		
		$fs = array(
			'barcode' 		=> true,
			'name' 			=> true,
			'spec'			=> true,
		);
		if($this->model_data_cache == null){
			$col_values = $this->getColumnData($col, $model);
			$condition = array(
				'spec.id' => array('$in' => $col_values),
			);
			$data = $model->find($condition, $fs);
			if(!empty($data)){
				foreach ($data as $d){
					foreach ($d['spec'] as $spec){
						$_spec = $d;
						if(!empty($spec['spec1'])){
							$_spec['name'] .= ' ' . $spec['spec1'];
						}
						if(!empty($spec['spec2'])){
							$_spec['name'] .= ' ' . $spec['spec2'];
						}
						if(!empty($spec['retail_price'])){
							$_spec['retail_price'] = $spec['retail_price'];
						}else{
							$_spec['retail_price'] = 0;
						}
						if(!empty($spec['wholesale_price'])){
							$_spec['wholesale_price'] = $spec['wholesale_price'];
						}else{
							$_spec['wholesale_price'] = 0;
						}
						unset($_spec['spec']);
						$this->model_data_cache[$spec['id']] = $_spec;
					}
				}
			}
		}
		
		$col = $this->getColumnNum($col);
		
		$conf = array(
			'barcode',
			'name'
		);
		$this->_parse_col_val($conf, $col);

		return $this
			->setColTitle($col, 		'商品条码')
			->setColTitle($col + 1, 	'商品名称');
	}
	
	/**
	 * 从自个维护的供应商商品中获取商品信息
	 * @param $supplier_col 默认为'A'，也可以是数组：array('ventor'=>'544593E1D3DF2BF96638BC9019DD82AF')
	 * @param $goods_spec_col 商品规格ID所在列
	 */
	public function quickParseSupplierManualGoodsMongo($supplier_col = 'A', $goods_spec_col = 'B'){
		
	}
	
	/**
	 * 从配送供应商商品中获取商品信息
	 * 主要用于自动转仓库入库单
	 * @param $supplier_col 默认为'A'，也可以是数组：array('ventor'=>'544593E1D3DF2BF96638BC9019DD82AF')
	 * @param $goods_spec_col 商品规格ID所在列
	 */
	public function quickParseSupplierDeliveGoodsMongo($supplier_col = 'A', $goods_spec_col = 'B'){
		
	}
	
	/**
	 * 从自个供应商商品获取商品信息
	 * @param string $col
	 * @param string $supplier_id
	 * @return YModelDataGrid|Ambigous <YModelDataGrid, YModelDataGrid>
	 */
	public function quickParseSupplierGoodsMongo($col = 'A', $supplier_id = null){
		if($supplier_id === null){
			//尝试使用当前用户
			$supplier_id = Yii::app()->user->supplier_id;
		}
		if(empty($supplier_id)){
			return $this;
		}
		
		$model = new SupplierGoodsModel;
		$fs = array(
			'barcode' 		=> true,
			'code' 			=> true,
			'name' 			=> true,
			'goods_spec_id' => true,
		);
		if($this->model_data_cache == null){
			$col_values = $this->getColumnData($col, $model);
			$condition = array(
				'supplier_id' 	=> $supplier_id,
				'goods_spec_id' => array('$in' 			=> $col_values),
			);
			$data = $model->find($condition, $fs);
			if(!empty($data)){
				foreach ($data as $d){
					$this->model_data_cache[$d['goods_spec_id']] = $d;
				}
			}
		}
		
		$col = $this->getColumnNum($col);
		
		$conf = array(
			'barcode',
			'code',
			'name'
		);
		$this->_parse_col_val($conf, $col);
		return $this
			->setColTitle($col, '商品条码')
			->setColTitle($col + 1, '商品编码')
			->setColTitle($col + 2, '商品名称');
	}
	
	private function _parse_col_val($conf, $col){
		$col_index = $col + $this->keys_count - 1;
		for($row_index = $this->title_row, $c = count($this->data); $row_index < $c; ++$row_index){
			$id = $this->data[$row_index][$col_index]['v'];
			if(isset($this->model_data_cache[$id])){
				$d = $this->model_data_cache[$id];
			}else{
				$d = array();
			}
			
			$i = 0;
	
			$keys = $this->getRowKeys($row_index);
			if($keys!==null){
				$key = $keys[0];
			}
			
			foreach ($conf as $field => $val){
				$data = $d;
				if(is_int($field)){
					//直接指定列名
					$field = $val;
					$val = 'php:$data';
				}
				$fields = explode('.', $field);
				for($j = 0, $fc = count($fields); $j < $fc; ++$j){
					$f = $fields[$j];
					if(isset($data[$f])){
						$data = $data[$f];
					}else{
						$data = '';
						break;
					}
				}
				if($row_index == $this->title_row && $this->title_row > 0){
					//添加标题
					$column_title = $field;
			
					if($i == 0){
						$this->data[0][$col - 1] = array(
							'v' => $column_title,
							't' => self::$TYPE_TITLE,
						);
					}else{
						$this->data[0] = $this->_insertArrayValue($this->data[0], array(
							'v' => $column_title,
							't' => self::$TYPE_TITLE,
						), $col + $i - 1);
					}
				}
			
				if($val instanceof YDataGridCell){
					//支持动态类执行
					$keys = $this->getRowKeys($row_index);
					if($keys!==null){
						$val->key = $keys[0];
						$val->keys = $keys;
					}
					if($val->grid === null){
						$val->grid = &$this;
					}
					$val->row_index = $row_index;
					$val->col_index = $col_index;
					$val->cell_data = $data;
					$val = $val->toString();
				}elseif(strpos($val, 'php:') === 0){
					$val = substr($val, 4);
					@eval("\$val = $val;");
				}elseif(strpos($val, 'exp:') === 0){
					$val = substr($val, 4);
					//执行表达式
					$val = $this->_exec(null, null, $val, $data);
				}
				$new_data = array(
						't' => self::$TYPE_STRING,
						'v' => $val,
				);
				if($i == 0){
					$this->data[$row_index][$col_index] = $new_data;
				}else{
					$this->data[$row_index] = $this->_insertArrayValue($this->data[$row_index], $new_data, $col_index + $i);
				}
				++$i;
			}
		}
	}

	/**
	 * @deprecated
	 * @param string $col
	 * @return YModelDataGrid
	 */
	public function quickParseGoods($col = 'A'){
		$colNum = self::getColumnNum($col);
		return $this->parse('GoodsSpec', $col, array(
			'gid'			=> 'php:$data>1?$data:"--"',
			'goods.barcode',
			'id'			=> 'php:GoodsSpec::getFullName($data)',
		))
		->setColTitle($colNum + 2, '商品名称');
	}

	/**
	 * @deprecated
	 * @param string $col
	 * @return YModelDataGrid
	 */
	public function quickParseStoreGoods($col = 'A'){
		return $this->parse('StoreGoods', $col, array(
			'gid'			=> 'php:$data>1?$data:"--"',
			'barcode',
			'code'			=> 'php:empty($data)?"--":$data',
			'name',
		));
	}

	public function quickParseStoreGoodsMongo($col = 'A'){
		$colNum = self::getColumnNum($col);
		$this->parseMongo('StoreGoodsModel', $col, array(
			'barcode',
			'code'			=> 'php:empty($data)?"--":$data',
			'name',
// 			'units.1.name',
		));
		return $this
			->setColTitle($colNum, '商品条码')
			->setColTitle($colNum + 1, '商品编码')
			->setColTitle($colNum + 2, '商品名称')
// 			->setColTitle($colNum + 3, '商品单位')
		;
	}
}

?>