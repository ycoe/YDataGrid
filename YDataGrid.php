<?php
if(!defined('YDATA_GRID_SPLIT')){
	define('YDATA_GRID_SPLIT', ' ');
}

class YDataGrid{
	/**
	 * 操作的数据
	 * @var array
	 */
	protected $data = null;

	/**
	 * 表格属性
	 * @var unknown
	 */
	protected $table_attr = array();

	/**
	 * 标题列数
	 * @var int
	 */
	protected $title_row = 0;

	/**
	 * 键占有列
	 * @var int
	 */
	protected $keys_count = 0;

	/**
	 * 数据源
	 * @var unknown_type
	 */
	protected $dataProvider = null;

	/**
	 * 配置文件
	 * @var array
	 */
	protected $config = null;

	/**
	 * 表格标题
	 * @var string
	 */
	protected $caption = null;

	/**
	 * @var string
	 */
	protected $id = null;

	/**
	 * 初始化DataTable2
	 * @param unknown_type $dataProvider 数据源
	 * @param array $config 配置
	 */
	public function __construct($dataProvider=null, $config=null){
		if($dataProvider instanceof YDataGrid){
			$this->initNewEntity($dataProvider);
		}else if($dataProvider!==null){
			$this->dataProvider = $dataProvider;
			$this->config = $config;

			self::_init_data();

			self::_clean_data();
		}
	}

	//-----------------------公共方法---------------------
	public function setId($id){
		$this->id = $id;
		return $this;
	}

	/**
	 * 设置YDataGrid属性
	 * @param array $attrs
	 */
	public function setTableAttr($attrs){
		$this->table_attr = $attrs;
		return $this;
	}

	/**
	 * 设置为可排序
	 * @param unknown_type $cols 列名或列名的数组
	 * @param boolean $sort_column_name，排序是否使用列原始名称
	 */
	public function setSortAble($cols, $sort_column_name=false){
		if($this->title_row === 0){
			//未设置标题的不支持排序
			return $this;
		}
		if(is_array($cols)){
			foreach($cols as $col){
				$col_index = $this->getColumnNum($col) - 1;
				$this->data[0][$col_index]['sort_able'] = true;
				$this->data[0][$col_index]['sort_column_name'] = $sort_column_name;
			}
			return $this;
		}
		$col_index = $this->getColumnNum($cols) - 1;
		$this->data[0][$col_index]['sort_able'] = true;
		$this->data[0][$col_index]['sort_column_name'] = $sort_column_name;
	}

	/**
	 * 通过表格选择器设置表单元样式
	 * @param string $region
	 * @param string $clazz
	 */
	public function addClass($region, $clazz){
		$regions = $this->getCellByCode($region);
		foreach($regions as $reg){
			$row = $reg[0] + $this->title_row - 1;
			$col = $reg[1] + $this->keys_count - 1;
			$clazz = $this->getCellValue($col, $row, $clazz);
			if(isset($this->data[$row][$col]['class'])){
				//如果设置有样式，则进行合并
				$this->data[$row][$col]['class'] = $this->_mergerClass($this->data[$row][$col]['class'], $clazz);
			}else{
				$this->data[$row][$col]['class'] = $clazz;
			}
		}
		return $this;
	}

	/**
	 * 向某列或几列添加样式
	 * @param unknown_type $col 列名，或者列名的数组
	 * @param string $clazz 样式
	 * @param boolean $with_title 是否包含标题样式，默认为false
	 */
	public function addColClass($col, $clazz=null, $with_title=false){
		$cols = array();
		$ext_col = $this->keys_count - 1;
		if(is_string($col)){
			$cols[] = self::getColumnNum($col) + $ext_col;
		}else{
			if($clazz===null){
				foreach($col as $c=>$claz){
					$this->addColClass($c, $claz, $with_title);
				}
				return $this;
			}else{
				foreach($col as $c){
					$cols[] = self::getColumnNum($c) + $ext_col;
				}
			}
		}
		for($row_index = 0, $count = count($this->data);$row_index < $count; ++$row_index){
			if($row_index < $this->title_row && !$with_title){
				//标题
				continue;
			}
			foreach($cols as $col_index){
				$row = $this->data[$row_index];
				if($row_index < $this->title_row){
					//如果是标题行时
					$col_index -= $this->keys_count;
				}
				if(isset($row[$col_index]['class'])){
					//如果设置有样式，则进行合并
					$this->data[$row_index][$col_index]['class'] = $this->_mergerClass($row[$col_index]['class'], $clazz);
				}else{
					$this->data[$row_index][$col_index]['class'] = $clazz;
				}
			}
		}
		return $this;
	}

	/**
	 * 向某一单元格添加样式
	 * @param unknown_type $row_index
	 * @param unknown_type $col_index
	 * @param unknown_type $clazz
	 */
	public function addCellClass($row_index, $col_index, $clazz){
		$col_index= self::getColumnNum($col_index) + $this->keys_count - 1;
		$row_index = $this->_getDataRow($row_index) + $this->title_row - 1;
		if(isset($this->data[$row_index][$col_index]['class'])){
			//如果设置有样式，则进行合并
			$this->data[$row_index][$col_index]['class'] = $this->_mergerClass($this->data[$row_index][$col_index]['class'], $clazz);
		}else{
			$this->data[$row_index][$col_index]['class'] = $clazz;
		}
		return $this;
	}

	/**
	 * 根据给定的区域字符，返回对应的位置坐标
	 * @param string $cellStr 区域表达式，比如：A、C2、4、A1:E3、A1:Z0、A:E分表表示
	 * 		A列、C2一个单元格、第4行、A1到E3的长方形位置、整个数据区域、A列至E列所有行
	 */
	public function getCellByCode($cellStr){
		$dataRowCount = $this->getDataRowCount();
		$dataColCount = $this->getDataColCount();
		$regionList = array();
		if(is_int($cellStr)){
			//如果是数字，即表示某行
			if($cellStr == 0)
				$cellStr = $dataRowCount;
			for($i = 1;$i<=$dataColCount;++$i){
// 				echo $cellStr . ',' . $i . '<br>';
				$regionList[] = array($cellStr, $i);
			}
			
			return $regionList;
		}

		//如果只是字母
		if(preg_match('/^[A-Za-z]+$/', $cellStr)) {
			$col = self::getColumnNum($cellStr); // 获得第几列（由1开始）
			for($i = 1;$i<=$dataRowCount;++$i){
				$regionList[] = array($i, $col);
			}
			return $regionList;
		}

		//如果只是字母+数字，表示某一个单元格
		if(preg_match('/^[A-Za-z]+\d+$/', $cellStr)){
			$colStr = preg_replace('/^([A-Za-z]+)(\d+)$/', '$1', $cellStr);
			$rowStr = intval(preg_replace('/^([A-Za-z]+)(\d+)$/', '$2', $cellStr));
			$col = self::getColumnNum($colStr); // 获得第几列（由1开始）
			$regionList[] = array($rowStr, $col);
			return $regionList;
		}

		//A:E
		if(preg_match('/^([A-Za-z]+):([A-Za-z]+)$/', $cellStr)) {
			$startColStr = preg_replace('/^([A-Za-z]+):([A-Za-z]+)$/', '$1', $cellStr);
			$endColStr = preg_replace('/^([A-Za-z]+):([A-Za-z]+)$/', '$2', $cellStr);
			$startCol = self::getColumnNum($startColStr); // 获得第几列（由1开始）
			$endCol = self::getColumnNum($endColStr); // 获得第几列（由1开始）
			if($startCol > $endCol){
				$temp = $endCol;
				$endCol = $startCol;
				$startCol = $temp;
			}
			for($col_index = $startCol; $col_index<=$endCol; ++$col_index){
				for($row_index = 1; $row_index <= $dataRowCount;++$row_index){
					$regionList[] = array($row_index, $col_index);
				}
			}
			return $regionList;
		}

		// A3:E5
		if(preg_match('/^[A-Za-z]+\d+:[A-Za-z]+\d+$/', $cellStr)){
			$startColStr = preg_replace('/^([A-Za-z]+)(\d+):([A-Za-z]+)(\d+)$/', '$1', $cellStr);
			$startRow = intval(preg_replace('/^([A-Za-z]+)(\d+):([A-Za-z]+)(\d+)$/', '$2', $cellStr));

			$endColStr = preg_replace('/^([A-Za-z]+)(\d+):([A-Za-z]+)(\d+)$/', '$3', $cellStr);
			$endRow = intval(preg_replace('/^([A-Za-z]+)(\d+):([A-Za-z]+)(\d+)$/', '$4', $cellStr));

			$startCol = self::getColumnNum($startColStr); // 获得第几列（由1开始）
			$endCol = self::getColumnNum($endColStr); // 获得第几列（由1开始）
			if($startCol > $endCol){
				//交换
				$temp = $endCol;
				$endCol = $startCol;
				$startCol = $temp;
			}
			if($startRow > $endRow){
				$temp = $endRow;
				$endRow = $startRow;
				$startRow = $temp;
			}
			for($i = $startCol; $i<=$endCol; ++$i){
				for($j = $startRow;$j<=$endRow;++$j){
					$regionList[] = array($j, $i);
				}
			}
			return $regionList;
		}
		return $regionList;
	}

	/**
	 * 获取某行的key值，以数组的方式返回
	 * @param unknown_type $row 行号，以1开始
	 */
	public function getRowKeys($row){
		if($this->keys_count === 0)
			return null;
		$row_index = $row + $this->title_row - 1;
		$keys = array();
		for($i=0;$i<$this->keys_count;++$i){
			$keys[] = $this->data[$row_index][$i]['v'];
		}
		return $keys;
	}

	/**
	 * 合并相同的列
	 * @param unknown_type $condition
	 */
	public function mergerSameCol($cols){
		if(is_array($cols)){
			foreach($cols as $col){
				$this->mergerSameCol($col);
			}
			return $this;
		}

		$col = $this->getColumnNum($cols) + $this->keys_count - 1;
		$count = count($this->data);
		$last_cell_val = null;
		$meger_count = 1;
		for($row_index = $this->title_row; $row_index < $count; ++$row_index){
			$v = $this->data[$row_index][$col]['v'];
			if($last_cell_val === $v){
				//合并
				++$meger_count;
				$this->data[$row_index - $meger_count + 1][$col]['rowspan'] = $meger_count;
			}else{
				$meger_count = 1;
			}

			$last_cell_val = $v;
		}

		return $this;
	}

	/**
	 * 设置单元格类型
	 * @param string $region 区域表达式，比如：A、C2、4、A1:E3、A1:Z0、A:E分表表示
	 * 		A列、C2一个单元格、第4行、A1到E3的长方形位置、整个数据区域、A列至E列所有行
	 * @param unknown_type $type
	 */
	public function setCellType($region, $type){
		$regions = $this->getCellByCode($region);
		foreach($regions as $reg){
			$row = $reg[0] + $this->title_row - 1;
			$col = $reg[1] + $this->keys_count - 1;
			$this->data[$row][$col]['t'] = $type;
		}
		return $this;
	}

	/**
	 * 翻转，T为标题，此方法要求至少要有一条数据记录
	 * T1 T2 T2 T4    T1 A1 A2 A3
	 * A1 B1 C1 D1    T2 B1 B2 B3
	 * A2 B2 C2 D2 => T3 C1 C2 C3
	 * A3 B3 C3 D3    T4 D1 D2 D3
	 */
	public function reversal(){
		if(empty($this->data) || empty($this->data[$this->title_row]))return $this;
		$new_data = array();
		for($row_index=0, $count = count($this->data); $row_index < $count; ++$row_index){
			for($col_index = $this->keys_count, $col_count = count($this->data[$this->title_row]); $col_index < $col_count; ++$col_index){
				$ci = $col_index;
				if($row_index === 0){
					//如果是标题行，则不占Key列
					$ci = $col_index - $this->keys_count;
				}
				$cell = $this->data[$row_index][$ci];
				if($row_index < $this->title_row){
					//如果是原来的标题框，则设置为普通框
					$cell['t'] = self::$TYPE_STRING;
				}
				if($col_index === $this->keys_count){
					//如果是原来的第一列数据，则转换为标题栏
					$cell['t'] = self::$TYPE_TITLE;
				}
				$new_data[$col_index - $this->keys_count][$row_index] = $cell;
			}
		}
		$this->data = $new_data;
		$this->title_row = 1;
		$this->keys_count = 0;
		return $this;
	}

	/**
	 * 设置列的值
	 * @param array $values array('A'=>12, 'B'=>'php:')
	 */
	public function setColumnValues($values){
		if($values === null || count($values)<1)
			return $this;
		foreach($values as $col=>$val){
			$this->setColumnValue($col, $val);
		}
		return $this;
	}

	/**
	 * 设置某一列的值
	 * @param unknown_type $col
	 * @param unknown_type $value
	 */
	public function setColumnValue($col, $value){
		$i = 0;
		if(empty($this->data))return $this;
		foreach($this->data as $row){
			$this->setCellValue($col, $i, $value);
			++$i;
		}
		return $this;
	}

	/**
	 * 删除一列
	 * @param unknown_type $col
	 */
	public function delCol($col){
		if(empty($this->data))return $this;
		if(is_array($col)){
			rsort($col);
			foreach ($col as $c){
				$this->delCol($c);
			}
			return $this;
		}

		$col = self::getColumnNum($col) + $this->keys_count - 1;
		$row_num = 0;
		foreach($this->data as $row){
			if($row_num < $this->title_row){
				//标题栏
				array_splice($this->data[$row_num], $col - $this->keys_count, 1);
			}else{
				array_splice($this->data[$row_num], $col, 1);
			}
			++$row_num;
		}
		return $this;
	}

	/**
	 * 插入列
	 * @param unknown_type $col 在$col中插入一列，插入后，它的列号即为$col，1开始，如果为0表示在插入到最后
	 * @param unknown_type $val 列的值，可以使用PHP或者公式
	 * @param string $column_title 列标题，如果不空，则使用列编号代替
	 */
	public function insertCol($col, $val, $column_title=null){
		if($this->data === null || count($this->data) === 0)return $this;
		$col = self::getColumnNum($col);
		if($col === 0){
			//0为最后一列
			$col = $this->getDataColCount() + 1;
		}
		if($column_title === null){
			$column_title = 'NEW';
		}
		$col += $this->keys_count - 1;
		$row_num = 0;
		foreach($this->data as $row){
			if($row_num < $this->title_row){
				//添加标题
				$this->data[$row_num] = $this->_insertArrayValue($this->data[$row_num], array(
					'v' => $column_title,
					't' => self::$TYPE_TITLE,
				), $col - $this->keys_count);
			}else{
				$new_val = $this->getCellValue($col, $row_num, $val);
				$this->data[$row_num] = $this->_insertArrayValue($this->data[$row_num], array(
					't' => self::$TYPE_STRING,
					'v' => $new_val,
				), $col);
			}
			++$row_num;
		}
		return $this;
	}

	/**
	 * 本方法会对一个数组对某一列进行步进累加，并将累加的结果添加到最后一列
	 * @param string $col
	 */
	public function insertStepSum($col){
		if(empty($this->data))
			return $this;
		if(is_array($col)){
			//如果是数组时
			foreach($col as $c){
				$this->insertStepSum($c);
			}
			return $this;
		}

		$sum = 0;
		for($row_num = $this->title_row, $count = count($this->data); $row_num < $count; ++$row_num){
			$cell_val = doubleval($this->getCellValue($col, $row_num - $this->title_row + 1));
			$sum += $cell_val;

			$this->data[$row_num][] = array(
				't' => self::$TYPE_DOUBLE,
				'v' => $sum,
			);
		}
		$titleName = $col;
		$this->data[0][] = array(
			't' => self::$TYPE_TITLE,
			'v' => 'STEP_SUM(' . $this->_getTitleName($col) . ')',
		);
		return $this;
	}

	/**
	 * 根据指定的列名，重新排序
	 * $data_table->resetColumns(array('C', 'A', 'F'))
	 * 以上代码执行后，将将原来的C列当成第一列，A列成为第二列，F列成为第三列....，其它未被提及的将被删掉
	 * 也可以重复，比如：$data_table->resetColumns(array('C', 'A', 'C'))
	 * @param array $cols 里面的元素可以是Excel列名，也可以是数字，从0开始
	 */
	public function resetColumns($cols){
		if(empty($this->data))
			return $this;
		$temp_data = array();
		for($row_index=0, $c=count($this->data); $row_index<$c; ++$row_index){
			$row = $this->data[$row_index];
			if($row_index >= $this->title_row && $this->keys_count > 0){
				//复制Key
				for ($key_index=0; $key_index < $this->keys_count; ++$key_index){
					$temp_data[$row_index][$key_index] = $this->data[$row_index][$key_index];
				}
			}
			//复制值
			foreach($cols as $col){
				if(is_string($col)){
					$col = self::getColumnNum($col); //从1开始
				}
				if($row_index < $this->title_row){
					--$col;
				}else{
					$col += $this->keys_count - 1;
				}
				$temp_data[$row_index][] = $this->data[$row_index][$col];
			}
		}
		$this->data = $temp_data;
		return $this;
	}

	/**
	 * 复制一份DataTable
	 */
	public function cloneDataTable(){
		return clone $this;
	}

	/**
	 * 根据指定列进行合并
	 * @param array $group_cols 比如：array('A'=>SORT_ASC, 'B'=> SORT_DESC)
	 *
	 * @param array $cols
	 * array(
	 *	'A:' . self::$GROUP_SUM,
	*	'A:' . self::$GROUP_MERGE
	 * )
	 *  以1开始
	 * @param $merge_str 如果使用$GROUP_MERGE合并时的连接字符
	 * @param $keys 用于新YDataGrid的Key，当前仅支持self::$GROUP_FIRST类型的使用！
	 */
	public function groupBy($group_cols, $cols, $merge_str = ',', $keys=array()){
		if(empty($this->data))
			return $this;
		$temp_titles = array();

		// 首先必须对需要Group的列进行排序
		$this->sort($group_cols);

		$group_cols = self::_formatSortColumns($group_cols);

		$new_cols = array();
		foreach($cols as $c){
			$col_type = explode(':', $c);
			$col_index = $this->getColumnNum($col_type[0]) -1;
			$new_cols[] = array($col_index => intval($col_type[1]));
		}

		$newTitles = array();
		$i = 0;
		foreach($new_cols as $n_c){
			foreach($n_c as $c => $t){
				$col_name = ($this->title_row > 0) ? $this->data[0][$c]['v'] : '%' . self::getColumnCode($c);
				switch ($t){
					case self::$GROUP_FIREST:
						$newTitles[] = array(
							'v' => 'first('.$col_name.')',
							't' => self::$TYPE_TITLE,
						);
						break;
					case self::$GROUP_MERGE:
						$newTitles[] = array(
							'v' => 'merge('.$col_name.')',
							't' => self::$TYPE_TITLE,
						);
						break;
					case self::$GROUP_SUM:
						$newTitles[] = array(
							'v' => 'sum('.$col_name.')',
							't' => self::$TYPE_TITLE,
						);
						break;
					case self::$GROUP_COUNT:
						$newTitles[] = array(
							'v' => 'count('.$col_name.')',
							't' => self::$TYPE_TITLE,
						);
						break;
					default:
						$newTitles[] = array(
							'v' => $col_name,
							't' => self::$TYPE_TITLE,
						);
						break;
				}
			}
			++$i;
		}

		$temp_data = array($newTitles);
		$last_col_val = null;
		$row_new = 0;
		for($row_index=$this->title_row, $c=count($this->data); $row_index < $c; ++$row_index){
			$row = $this->data[$row_index];
			$cur_col_vals = '';
			foreach($group_cols as $col_index=>$ct){
				$cur_col_vals .= $this->data[$row_index][$col_index]['v'];//($col_index, $row_index);
			}
			$act = 0; // 0：新增， 1：合并
			if($last_col_val == $cur_col_vals){
				//合并
				$act = 1;
			}else{
				$last_col_val = $cur_col_vals;
				//新增行
				$act = 0;
				++$row_new;
			}

			$new_col = count($keys);
			foreach ($new_cols as $n_c){
				foreach($n_c as $col_index => $merger_type){
					$val = $this->data[$row_index][$col_index + $this->keys_count]['v'];//getCellValue($col_index + 1, $row_index);
					if($act === 0){
						//新增记录
						//添加Keys
						foreach($keys as $key){
							$temp_data[$row_new][] = array(
								'v' => $val,
								't' => self::$TYPE_KEY,
							);
						}
						if($merger_type === self::$GROUP_COUNT){
							$temp_data[$row_new][$new_col] = array(
								't' => self::$TYPE_STRING,
								'v' => 1,
							);
						}else{
							$temp_data[$row_new][$new_col] = array(
								't' => self::$TYPE_STRING,
								'v' => $val,
							);;
						}
					}else{
						//如果是合并，则需要判断合并类型
						switch ($merger_type){
							case self::$GROUP_FIREST:
								//以取第一个值
								break;
							case self::$GROUP_MERGE:
								//合并
								$temp_data[$row_new][$new_col]['v'] .= $merge_str . $val;
								break;
							case self::$GROUP_SUM:
								//求和
								$temp_data[$row_new][$new_col]['t'] = self::$TYPE_STRING;
								$temp_data[$row_new][$new_col]['v'] += $val;
								break;
							case self::$GROUP_COUNT:
								//计数
								$temp_data[$row_new][$new_col]['t'] = self::$TYPE_STRING;
								$temp_data[$row_new][$new_col]['v'] += 1;
								break;
							default:
								break;
						}
					}
					++$new_col;
				}
			}
		}
		$this->keys_count = count($keys);
		$this->data = $temp_data;
		$this->title_row = 1;
// 		$this->setTitles($newTitles);
		return $this;
	}

	/**
	 * 截取DataTable中的部分数据作为新的数据
	 * @param unknown_type $start 开始行，以1开始
	 * @param unknown_type $count 截取数据行数
	 */
	public function slice($start, $count){
		if($this->data == null || count($this->data) === 0)return $this;
		$start = $this->_getDataRow($start) - 1;
		echo $start;
		$max_row = $this->getDataRowCount();
		if($count > $max_row){
			$count = $max_row;
		}
		if($count<=0){
			$this->data = $this->getTitleGrids();
			return $this;
		}

		$data = array_slice($this->data, 0, $this->title_row);
		if($start >= 0){
			$endData = array_slice($this->data, $this->title_row + $start, $count);
			foreach($endData as $d){
				$data[] = $d;
			}
		}
		$this->data = $data;
		return $this;
	}

	/**
	 * 设置列名
	 * @param unknown_type $col
	 * @param unknown_type $title
	 */
	public function setColTitle($col, $title){
		if(is_string($col)){
			$col_index = $this->getColumnNum($col);
			$col = array($col_index=>$title);
		}else{
			$col = array($col=>$title);
		}
		foreach($col as $col_index=>$title){
			//$col_index = $this->getColumnNum($c);
			$this->data[0][$col_index - 1]['v'] = $title;
		}
		return $this;
	}

	/**
	 * 设置Grid的标题，这里仅简单支持一行标题行
	 * 如果标题长度大于数据长度，将被截取
	 * @param array $titles
	 */
	public function setTitles($titles){
		$data_count = $this->getDataColCount();
		if($data_count===0){
			$data_count = count($titles);
		}
		$title_grid = array();
		$i = 0;
		foreach ($titles as $title){
			if($i >= $data_count){
				break;
			}
			$title_grid[$i] = array(
				't' => self::$TYPE_TITLE,
				'v' => $title
			);
			++$i;
		}
		if($this->title_row === 0){
			//还没有标题
			if($this->data === null || count($this->data) === 0){
				//如果还没有设置有值
				$this->data = array($title_grid);
			}else{
				$title_grid = array($title_grid);
				foreach($this->data as $d){
					$title_grid[] = $d;
				}
				$this->data = $title_grid;
			}
		}else{
			//已经有标题
			$title_index = 0;
			foreach ($title_grid as $tg){
				if(isset($this->data[0][$title_index])){
					$this->data[0][$title_index] = array_merge($this->data[0][$title_index], $tg);
				}else{
					$this->data[0][$title_index] = $tg;
				}
				++$title_index;
			}
		}
		$this->title_row = 1;
		return $this;
	}

	/**
	 * 插入一行（数据行！）
	 * 如果插入的数据超过grid的列，则超出部分会被省略，如果不够，则会用空值填补
	 * 如果新添加行号已经存在，则会向下移动
	 * @param int $row_index，从1开始计，负数表示从后面开始，0表示最后一行
	 * @param unknown_type $data 数据
	 */
	public function addRow($data, $row_index=0, $default_type=null){
		if($default_type === null)
			$default_type = self::$TYPE_STRING;
		$row_index = self::_getDataRow($row_index);
		if($row_index>0){
			$row_index += $this->title_row;
		}
		$column_count = self::getDataColCount();
		$row_count = count($this->data);
		$new_row = array();
		for($i=0;$i<$this->keys_count;++$i){
			$new_row[] = array(
				't' => self::$TYPE_KEY,
				'v' => '',
			);
		}
		$i = 0;
		foreach ($data as $d){
			$new_row[] = array(
				't' => $default_type,
				'v' => $d,
			);
			if($i >= $column_count - 1){
				//超出数据长度，则将其截断
				break;
			}
			++$i;
		}
		if($row_index == 0){
			$this->data[] = $new_row;
		}else{
			self::_insertArrayValue($this->data, $new_row, $row_index - 1);
		}
		return $this;
	}

	/**
	 * 设置主标题
	 * @param unknown_type $caption
	 */
	public function setCaption($caption){
		$this->caption = $caption;
		return $this;
	}

	/**
	 * 对Grid进行排序
	 * @param unknown_type $sort_cols 可以是列名，也可以是列名的数组（列的Key是列名，值是SORT_ASC/SORT_DESC），默认是SORT_ASC升序排列
	 */
	public function orderBy($sort_cols){
		return self::sort($sort_cols);
	}

	/**
	 * 对Grid进行排序
	 * @param unknown_type $sort_cols 可以是列名，也可以是列名的数组（列的Key是列名，值是SORT_ASC/SORT_DESC），默认是SORT_ASC升序排列
	 */
	public function sort($sort_cols){
		if(empty($this->data))
			return $this;
		$sort_cols = $this->_formatSortColumns($sort_cols);

		$data = array_slice($this->data, $this->title_row);
		$this->data = array_slice($this->data, 0, $this->title_row);
		$data = self::_sortGrid($data, $sort_cols);
		foreach($data as $d){
			$this->data[] = $d;
		}
		return $this;
	}

	/**
	 * 是否有数据
	 */
	public function isHasData(){
		if(empty($this->data))
			return false;
		if($this->title_row > 0 && !isset($this->data[$this->title_row])){
			return false;
		}
		return true;
	}

	/**
	 * 通过条件，将一个DataTable作GroupBy操作，并将其根据GroupBy的条件分成1个或多个DataTable并返回
	 * @param unknown_type $group_cols
	 * @param unknown_type $cols
	 * @param unknown_type $merge_str
	 */
	public function groupToDataTables($group_cols){
		if(!$this->isHasData()){
			return array();
		}

		$temp_titles = array();

		if(is_string($group_cols)){
			$group_cols = array($group_cols => SORT_ASC);
		}
		// 首先必须对需要Group的列进行排序
		$this->sort($group_cols);

		$newDataTables = array();
		$last_col_val = null;
		$titleGrids = $this->getTitleGrids();
		$temp_data = $titleGrids;
		for($row_count = $this->title_row, $count = count($this->data); $row_count < $count; ++$row_count){
			$row = $this->data[$row_count];
			$cur_col_vals = '';
			foreach($group_cols as $col=>$ct){
				$cur_col_vals .= $this->getCellValue($col, $row_count);
			}
// 			if(empty($cur_col_vals))print_r($row);
// 			echo $cur_col_vals . '=>' . $row[2]['v'] . '<br>';
			if($last_col_val == $cur_col_vals){
				// 合并
				$temp_data[] = $row;
			}else{
				// 新增行
				if(count($temp_data)>$this->title_row){
					$new_table = new YDataGrid();
					$new_table->setData($temp_data);
					$newDataTables[$last_col_val] = $new_table;
					$temp_data = $titleGrids;
				}
				$last_col_val = $cur_col_vals;
				$temp_data[] = $row;
			}
		}
 		if(count($temp_data)>$this->title_row){
 			$new_table = new YDataGrid();
 			$new_table->setData($temp_data);
 			$newDataTables[$last_col_val] = $new_table;
 		}
		return $newDataTables;
	}

	/**
	 * 在现有的Grid右边连接另一个Grid
	 *
	 * @param YDataGrid $grid
	 * @param unknown $join_on
	 * @param unknown $default_val
	 * @return YDataGrid
	 */
	public function rightJoin(YDataGrid $grid, $join_on, $default_val){
		if(empty($this->data))
			return $this;
		$row_count = count($this->data);

		foreach ($join_on as $l_col=>$r_col){}

		$grid_data = $grid->getData();
		$grid_title_row = $grid->getTitleCount();
		$grid_data_count = count($grid->getData());
		if($grid_title_row > 0 && $this->title_row > 0){
			//合并标题，这里只支持一行标题
			foreach ($grid_data[0] as $t){
				$this->data[0][] = $t;
			}
		}
		for($row_index = $this->title_row; $row_index < $row_count; ++$row_index){
			$cell_val = $this->getCellValue($l_col, $row_index);

			for($g_row_index = $grid_title_row; $g_row_index < $grid_data_count; ++$g_row_index){
				$m_cell_val = $grid->getCellValue($r_col, $g_row_index);
				if($m_cell_val == $cell_val){
					//匹配上！
					for($i = $grid->getKeyCount(); $i < count($grid_data[$g_row_index]); ++$i){
						$cell = $grid_data[$g_row_index][$i];
						$this->data[$row_index][] = $cell;
					}
					continue 2;
				}
			}
			//没有匹配上的，使用默认值填充！
			for($i = 0; $i < $grid->getDataColCount(); ++$i){
				$val = $this->getCellValue($i, $row_index, $default_val);
				$this->data[$row_index][] = array(
					'v' => $val,
					't' => YDataGrid::$TYPE_STRING,
				);
			}
		}
		return $this;
	}

	/**
	 * 左连接，使用左连接，会将行的顺序改为连接的数组顺序，不匹配的数据将被删除。左连接中不匹配的数据将使用默认值填充
	 * 本方法不改变原有Grid的结构
	 * @param array|YDataGrid $joined_data
	 * @param array $join_on_cols 连接条件，目前仅支持一个条件且仅限"等于"操作
	 * 比如：array('A'=>'B')表示$left_data中的A列DataTable中的B列时合并
	 * 如果$left_data是一维数组时，也可以使用单个字符表示，此一维数组与DataTable中的此列相等
	 * @param $default_val 如果连接不上时的默认值
	 */
	public function leftJoin($joined_data, $join_on_cols, $default_val=''){
		if(empty($this->data))
			return $this;

		$join_data = null;
		if($joined_data instanceof YDataGrid){
			$join_data = $joined_data->getData();
			$title_count = $joined_data->getTitleCount();
			if($title_count > 0){
				//如果有标题时，将标题删除
				$join_data = array_slice($join_data, $title_count);
			}
		}else{
			if(empty($joined_data) || count($joined_data) === 0){
				//如果左边数组为空，则返回空数组
				$this->data = null;
				$this->title_row = 0;
				$this->keys_count = 0;
				return $this;
			}
			$join_data = self::_toBaseGridArray($joined_data);
		}

		$new_grid = $this->getTitleGrids();
		foreach ($join_data as $data){
			$new_grid[] = $data;
		}

		if(is_array($join_on_cols)){
			$l_c = key($join_on_cols);
			$l_c_n = 0;//self::getColumnNum($l_c) - 1;
			$r_c_n = self::getColumnNum($join_on_cols[$l_c]) - 1;
		}else{
			$l_c_n = 0;
			$r_c_n = self::getColumnNum($join_on_cols) - 1;
		}
		//$l_c_n += $this->keys_count;
		$r_c_n += $this->keys_count;


		//连接
		$col_count = $this->getDataColCount();
		$new_row_index = 0;
		$new_row_count = count($new_grid);
		for($new_row_index = $this->title_row; $new_row_index < $new_row_count; ++$new_row_index){
			$new_row = $new_grid[$new_row_index];
			$join = false;
			$title_row = $this->title_row;
			$row_count = count($this->data);
			for($row_index = $this->title_row; $row_index < $row_count; ++$row_index){
				$row = $this->data[$row_index];
				if($new_row[$l_c_n]['v'] == $row[$r_c_n]['v']){
					$join = true;
					//如果相等，则将数据追加到新数组上去
					$new_grid[$new_row_index] = $row;
					break;
				}
			}
			if(!$join){
				//使用默认值填充
				for($i=0; $i < $col_count + $this->keys_count; ++$i){
					$v = $default_val;
					if($i === $r_c_n){
						$v = $new_row[$l_c_n]['v'];
					}
					$new_grid[$new_row_index][$i] = array(
						't' => self::$TYPE_STRING,
						'v' => $v,
					);
				}
			}
		}
		$this->data = $new_grid;
		return $this;
	}

	/**
	 * 获取Grid标题行，以Grid Array方式返回
	 */
	public function getTitleGrids(){
		if($this->title_row > 0){
			return array_slice($this->data, 0, $this->title_row);
		}
		return null;
	}


	/**
	 * 获取某一列的值（不包含标题）
	 * @param string | int $col 列名（可以为是数字或字母列名）
	 * @param MongoModel $mongo_mod MongoModel，用于转换id
	 * @return array
	 */
	public function getColumnData($col, $mongo_mod=null){
		if(is_string($col)){
			$col_index = $this->getColumnNum($col);
		}else{
			$col_index = $col;
		}
		$col_index += $this->keys_count - 1;
		$col_datas = array();
		for($row_index = $this->title_row, $c = count($this->data); $row_index < $c; ++$row_index){
			$v = $this->data[$row_index][$col_index]['v'];
			if($mongo_mod != null){
				$v = $mongo_mod->get_id($v);
			}
			$col_datas[] = $v;
		}
		return $col_datas;
	}

	/**
	 * 获取某列值，以浮点形的数组返回
	 * @param unknown_type $col
	 */
	public function getColumnDoubleData($col){
		if(empty($this->data))return array();
		$col = self::getColumnNum($col) + $this->keys_count - 1;
		$data = array();
		$row_count = $this->getDataRowCount();
		for($i = $this->title_row; $i <= $row_count; ++$i){
			$cell_val = $this->data[$i][$col]['v'];
			$data[] = doubleval($cell_val);
		}
		return $data;
	}

	/**
	 * 获取某列值，以整形的数组返回
	 * @param unknown_type $col
	 */
	public function getColumnIntData($col){
		if(empty($this->data))return array();
		$col = self::getColumnNum($col) + $this->keys_count - 1;
		$data = array();
		$row_count = $this->getDataRowCount();
		for($i = $this->title_row; $i <= $row_count; ++$i){
			$cell_val = $this->data[$i][$col]['v'];
			$data[] = intval($cell_val);
		}
		return $data;
	}

	/**
	 * 获取某一列的和
	 * @param unknown_type $col
	 */
	public function getColSum($col){
		if(empty($this->data))return 0;
		$sum = 0;
		$row_count = $this->getDataRowCount();
		if(strpos($col, 'exp:') === 0){
			$val = substr($col, 4);
			for($i=$this->title_row;$i <= $row_count;++$i){
				//执行表达式
				$val = $this->_exec(0, $i, $val);
				$sum += $val;
			}

		}else{
			$col = self::getColumnNum($col) + $this->keys_count - 1;
			for($i=$this->title_row;$i <= $row_count;++$i){
				$cell_val = $this->data[$i][$col]['v'];
				$sum += doubleval($cell_val);
			}
		}
		return $sum;
	}

	/**
	 * 获取数据记录项
	 */
	public function getDataRowCount(){
		if(empty($this->data))return 0;
		return count($this->data) - $this->title_row;
	}

	/**
	 * 获取Grid标题行，以标题名称的Array方式返回
	 */
	public function getTitles(){
		$titleGrids = self::getTitleGrids();
		if($titleGrids===null){
			return null;
		}
		$titles = array();
		foreach($titleGrids[0] as $tg){
			$titles[] = $tg['v'];
		}
		return $titles;
	}

	/**
	 * 设置某一单元格的值
	 * @param unknown_type $col 列，可以是数字或者是列名（从1开始）
	 * @param unknown_type $row 行数，从1开始
	 * @param unknown_type $val 值
	 */
	public function setCellValue($col, $row, $val){
		$col = self::getColumnNum($col);
		$row = self::_getDataRow($row);
		if($row === 0)return $this;
		$v = $this->getCellValue($col, $row, $val);
		$this->data[$row + $this->title_row - 1][$col + $this->keys_count - 1]['v'] = $v;
		return $this;
	}

	/**
	 * 获取某个单元格的值
	 * @param unknown_type $col 以1开始
	 * @param unknown_type $row 以1开始
	 * @param unknown_type $val 可以输入公式或者PHP代码到本单元格中计算
	 */
	public function getCellValue($col, $row, $val=null){
		if(empty($this->data))
			return null;
		$col = self::getColumnNum($col);

		$row = self::_getDataRow($row);
		if($row === 0)return null;
		if($val === null){
			$col += $this->keys_count - 1;
			$row += $this->title_row - 1;
			if(isset($this->data[$row][$col]['v'])){
				return $this->data[$row][$col]['v'];
			}else{
				return null;
			}
		}elseif(is_array($val)){
			//如果是数组
			$v_s = '';
			$val = array_filter($val);
			foreach($val as $k=>$v){
				if($v === null)continue;
				$val[$k] = $this->getCellValue($col, $row, $v);
			}
			return implode(YDATA_GRID_SPLIT, $val);
		}elseif($val instanceof YDataGridCell){
			//支持动态类执行
			$keys = $this->getRowKeys($row);
			if($keys!==null){
				$val->key = $keys[0];
				$val->keys = $keys;
			}
			if($val->grid === null){
				$val->grid = &$this;
			}
			$val->row_index = $row;
			$val->col_index = $col;
			$val->cell_data = $this->getCellValue($col, $row);
			$val = $val->toString();
			return $val;
		}else{
			if(strpos($val, 'php:') === 0){
				$data = $this->getCellValue($col, $row);
				$keys = $this->getRowKeys($row);
				if($keys!==null){
					$key = $keys[0];
				}
				$val = substr($val, 4);
				@eval("\$val = $val;");
			}elseif(strpos($val, 'exp:') === 0){
				$val = substr($val, 4);
				//执行表达式
				$val = $this->_exec($col, $row, $val);
			}
			return $val;
		}
	}

	/**
	 * 获取数据列长度，如果标题行下第一行有数据，则使用第一行的列数，否则则为0
	 */
	public function getDataColCount(){
		if(isset($this->data[$this->title_row])){
			return count($this->data[$this->title_row]) - $this->keys_count;
		}else{
			if($this->title_row > 0){
				//如果设置有标题时，使用标题的数量
				return count($this->data[$this->title_row - 1]);		
			}else{
				return 0;
			}
		}
	}

	/**
	 * 获取当前DataGrid的所有数据
	 */
	public function getData(){
		return $this->data;
	}

	/**
	 * 设置Grid的数据
	 * @param array $data
	 */
	public function setData($data){
		$this->data = $data;
		//判断标题行
		foreach($this->data as $row_index=>$row){
			if(isset($row[0]['t']) && $row[0]['t'] == self::$TYPE_TITLE){
				$this->title_row = $row_index + 1;
				continue;
			}
		}

		//判断列
		if(isset($this->data[$this->title_row])){
			foreach($this->data[$this->title_row] as $col_index=>$cell){
				if(isset($cell['t']) && $cell['t'] == self::$TYPE_KEY){
					$this->keys_count = $col_index + 1;
					continue;
				}
			}
		}
	}

	/**
	 * 获取标题行数
	 */
	public function getTitleCount(){
		return $this->title_row;
	}

	/**
	 * 获取键列数量
	 */
	public function getKeyCount(){
		return $this->keys_count;
	}

	/**
	 * 快速显示成HTML表格
	 * @param unknown_type $debug
	 */
	public function render($debug=true){
		return $this
			->toYHtmlDataGrid()
			->render($debug);
	}

	/**
	 * 转换为YExcelDataGrid对象
	 * @return YHtmlDataGrid
	 */
	public function toYHtmlDataGrid(){
		$yHtmlDataGrid = new YHtmlDataGrid($this);
		return $yHtmlDataGrid;
	}

	/**
	 * 转换为YExcelDataGrid对象
	 * @return YExcelDataGrid
	 */
	public function toYExcelDataGrid(){
		$yExcelDataGrid = new YExcelDataGrid($this);
		return $yExcelDataGrid;
	}

	/**
	 * 转换为YCSVDataGrid对象
	 * @return YCSVDataGrid
	 */
	public function toYCSVDataGrid(){
		$yCSVDataGrid = new YCSVDataGrid($this);
		return $yCSVDataGrid;
	}

	/**
	 * 转换为YStoreGoodsDataGrid对象
	 * @return YStoreGoodsDataGrid
	 */
	public function toYStoreGoodsDataGrid(){
		$yYStoreGoodsDataGrid = new YStoreGoodsDataGrid($this);
		return $yYStoreGoodsDataGrid;
	}

	/**
	 * 转换为YModelDataGrid对象，本对象可以通过分列的方法，快速补充对象信息，比如商品条码、站内码、店内码等...
	 * 使用不同的对象，可以获得不同的属性，详见对应的model类
	 * @return YModelDataGrid
	 */
	public function toYModelDataGrid(){
		$yYModelDataGrid = new YModelDataGrid($this);
		return $yYModelDataGrid;
	}

	/**
	 * 向DataGrid中的数据转换成某类，以数组方式返回
	 * @param string $modelClass Model类名
	 * @param array $mapping 映射表，比如：array('A'=>'goods_id', 'B'=>'supplier_id' ...)
	 */
	public function toModels($modelClassName, $mapping, $default_datas=null){
		if (empty($this->data))return null;
		$map = array();
		foreach ($mapping as $col => $fieldName){
			$colNum = $this->getColumnNum($col);
			$map[$colNum - 1] = $fieldName;
		}

		$row_count = $this->getDataRowCount();
		$models = array();
		for ($i = 0; $i < $row_count; ++$i){
			$row = $this->data[$i + $this->title_row];
			$m = new $modelClassName;
			foreach ($map as $col => $fieldName){
				$m->$fieldName = $row[$col + $this->keys_count]['v'];
			}
			if(!empty($default_datas)){
				foreach ($default_datas as $fieldName => $data){
					$m->$fieldName = $data;
				}
			}
			$models[] = $m;
		}
		return $models;
	}

	/**
	 * 获取HighRollerChar对象
	 * @param sring $charType Char类型
	 * @param array $config Char配置
	 */
	public function getChart($charType, $config=null){
		$char_name = 'HighRoller' . ucfirst($charType) . 'Chart';
		$char = new $char_name;
		if($config!==null && count($config) > 0){
			foreach ($config as $name=>$values){
				foreach ($values as $k=>$v){
					$char->$name->$k = $v;
				}
			}
		}
		return $char;
	}

	/**
	 * 将Excel中的列名转换成对应的数字，比如A=>1, AA=>27 ....
	 * 根据Excel，列不应该超出IV，即是255列
	 * @param unknown_type $code，可以使用列编号，比如：A、B、AC...与Excel一致
	 * 	也可以使用数字，负数表示倒数第几个，比如：-1表示最后一列
	 * 	如果列数超过数据总列数，则使用最后一列
	 */
	public function getColumnNum($code){
		$count = $this->getDataColCount();
		if(is_int($code)){
			if($count <= 0){
				return 0;
			}
			while($code<0){
				$code += $count;
			}
			if($code > $count){
				$code = $count;
			}
			return $code;
		}
		if(!preg_match('/^[A-Z]+$/i', $code)){
			return false;
		}
		if(self::$CODE_COLUMNS === null){
			self::$CODE_COLUMNS = array_flip(self::$COLUMN_CODES);
		}
		$num = 0;
		$p = 0;
		while(strlen($code)>0){
			$b = substr($code, 0, 1);
			$code = substr($code, 1);
			if($p>0){
				$num = $num * 26 + (self::$CODE_COLUMNS[$b] - 1);
			}else{
				$num = $num * 26 + self::$CODE_COLUMNS[$b];
			}
			++$p;
		}
		if($num > $count){
			$num = $count;
		}
		return $num;
	}

	//-----------------------受保护方法-----------------------
	/**
	 * 将父级的属性赋给子类
	 * @param unknown_type $curEntity 当前类实例
	 * @param YDataGrid $parentEntity 父类实例
	 */
	protected function initNewEntity(YDataGrid $parentEntity){
		$r = new ReflectionClass($parentEntity);
		$properties = $r->getProperties(ReflectionProperty::IS_PROTECTED);
		foreach ($properties as $property){
			$name = $property->name;
			$this->$name = &$parentEntity->$name;
		}
	}

	/**
	 * 根据提供的行号进行循环计算，获得已经存在的行号
	 * 0表示最后新增一行，直接返回
	 * 负数表示从最后一行算起，进行循环计算
	 * @param int $row_index 行数
	 */
	protected function _getDataRow($row_index){
		$count = count($this->data) - $this->title_row + 1;
		if($count === 0){
			return 0;
		}
		if($row_index < 0){
			while($row_index < 0){
				//如果是反向获取
				$row_index += $count;
			}
		}elseif($row_index === 0){
			//等于0

		}else{
			//大于0
			while($row_index > $count){
				$row_index -= $count;
			}
		}
		return $row_index;
	}

	//-----------------------私有方法-----------------------

	private static $COLUMN_CODES = array('', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
	private static $CODE_COLUMNS;

	public static $TYPE_KEY = 0;	//键值
	public static $TYPE_TITLE = 10;	//标题
	public static $TYPE_INT = 20;	//整型
	public static $TYPE_DOUBLE = 30;//浮点型
	public static $TYPE_STRING = 40;//字符型
	public static $TYPE_NULL = 50;	//空值

	public static $GROUP_FIREST = 1;//保留第一个
	public static $GROUP_SUM = 2;	//求合
	public static $GROUP_MERGE = 4; //合并，即串连起来
	public static $GROUP_COUNT = 8; //计数

	//-----------------------全局静态方法-----------------------
	/**
	 * 合并多个DataGrid
	 * 合并后会以第一个Grid为基本Grid，将其它Grid上的数据复制上去
	 * 本方法可以使用多个参数，每个参数都为结构一致的Grid
	 */
	public static function mergerDataGrid(){
		$dataGrids = func_get_args();
		if(empty($dataGrids)) return null;
		$dataGrid = null;
		$dataGridData = null;
		if(isset($dataGrids[0]) && is_array($dataGrids[0])){
			//如果传入的是一个YDataeGrid的数组
			$dataGrids = $dataGrids[0];
		}
		foreach($dataGrids as $grid){
			$data = $grid->getData();
			if($dataGrid === null){
				$dataGrid = $grid;
				$dataGridData = $data;
				continue;
			}
			$title_count = $grid->getTitleCount();
			for($row_index = $title_count, $count = count($data); $row_index < $count; ++$row_index){
				$dataGridData[] = $data[$row_index];
			}
		}
		$dataGrid->setData($dataGridData);
		return $dataGrid;
	}

	/**
	 * 根据
	 * @param unknown_type $rowset
	 * @param unknown_type $sort_cols
	 */
	public static function _sortGrid($rowset, $sort_cols){
		$sortArray = array();
		$sortRule = '';
		foreach ($sort_cols as $sortField => $sortDir)
		{
			foreach ($rowset as $offset => $row)
			{
				$sortArray[$sortField][$offset] = $row[$sortField]['v'];
			}
			$sortRule .= "\$sortArray['$sortField'], $sortDir, ";
		}
		if (empty($sortArray) || empty($sortRule)) {
			return $rowset;
		}
		eval('array_multisort(' . $sortRule . '$rowset);');
		return $rowset;
	}

	/**
	 * 根据提供的数值，获取对应的列名
	 * 根据Excel，列数不应该超出255列！不过本方法未对此作限制
	 * @param int $num
	 */
	public static function getColumnCode($num){
		if($num === 0)return '';
		$code = '';
		while ($num > 26){
			$s = $num % 26;
			if($s == 0){
				$s = 26;
			}
			$code = self::$COLUMN_CODES[$s] . $code;
			$num -= $s;
			$num /= 26;
		}
		$code = self::$COLUMN_CODES[$num] . $code;
		return $code;
	}

	//-----------------------以下开始为私有方法-----------------------
	private function _getTitleName($col){
		$col_num = self::getColumnNum($col);

		if($this->title_row > 0 && isset($this->data[0][$col_num]['v'])){
			$col_name = $this->data[0][$col_num]['v'];
		}else{
			$col_name = '%' . self::getColumnCode($col);
		}
		return $col_name;
	}

	/**
	 * 合并样式
	 * @param unknown_type $class1
	 * @param unknown_type $class2
	 */
	private function _mergerClass($class1, $class2){
		$class1 = array_merge(explode(' ', $class1), explode(' ', $class2));
		$class1 = array_unique($class1);
		return implode(' ', $class1);
	}

	/**
	 * 清除初始化数据
	 */
	private function _clean_data(){
		$this->dataProvider = null;
		$this->config = null;
	}

	/**
	 * 初始化数据
	 * @throws Exception
	 */
	private function _init_data(){
		if($this->dataProvider instanceof CActiveDataProvider){
			if($this->config['columns'] === null){
				$this->_initTitle($this->dataProvider->model->attributeNames());
			}else{
				$this->_initTitle($this->config['columns']);
			}
			$data = $this->dataProvider->getData();

			//转换成数组
			$this->_getDataProviderData($data);
		}else if($this->dataProvider instanceof IDataProvider){
			$data = $this->dataProvider->getData();
			if(isset($data[0]) && is_array($data[0])){
				if(!isset($this->config['columns'])){
					$this->_initTitle(array_keys($data[0]));
				}
				$this->_getDataProviderData($data);
			}
		}else if(is_array($this->dataProvider)){
			if(isset($this->dataProvider[0]) && $this->dataProvider[0] instanceof CActiveRecord){
				//如果是AR集合
				if(!isset($this->config['columns'])){
					//抛出错误
					throw new Exception('使用AR时，必须指定columns');
				}
				$this->_initTitle($this->config['columns']);
				$r = 0;
				$this->_getDataProviderData($this->dataProvider);
			}else{
				if(count($this->dataProvider)<=0){
					return;
				}
				//如果是数组，则可以将其拆分
				$columns = $this->config['columns'];
				if(!isset($columns) && count($this->dataProvider)>0){
					$columns = array_keys($this->dataProvider[0]);
				}
				$this->_initTitle($columns);
				$keys = array();
				if(isset($this->config['key'])){
					$keys = $this->config['key'];
					if(is_string($keys)){
						$keys = array($keys);
					}
					$this->keys_count = count($keys);
				}
				$row_index = 0;
				$columns = array_merge($keys, $columns);
				foreach($this->dataProvider as $row){
					$col_index = 0;
					foreach($columns as $col){
						if($col_index < $this->keys_count){
							//如果是KEY列
							$this->data[$row_index + $this->title_row][$col_index] = array(
								'v' => $this->_get_array_data_by_key($row, $col),//isset($row[$col])?$row[$col]:'',
								't' => self::$TYPE_KEY,
							);
						}else{
							$this->data[$row_index + $this->title_row][$col_index] = array(
								'v' => $this->_get_array_data_by_key($row, $col),//isset($row[$col])?$row[$col]:'',
								't' => self::$TYPE_STRING,
							);
						}
						++$col_index;
					}
					++$row_index;
				}
			}
		}
	}
	
	/**
	 * 获取数组中的值
	 * @param 数组 $data
	 * @param 数组键值 $key
	 * @return string|unknown
	 */
	private function _get_array_data_by_key($data, $key){
// 		$keys = split('\.', $key);
		$keys = preg_split('/\./', $key);
		foreach($keys as $k){
			if(isset($data[$k])){
				$data = $data[$k];
			}else{
				return '';
			}
		}
		return $data;
	}
	
	/**
	 * 初始化标题
	 * @param array $dataArray
	 */
	private function _initTitle($dataArray){
		$i = 0;
		foreach($dataArray as $title){
			$this->data[0][$i] = array(
				'v' => $title,
				's' => $title,
				't' => self::$TYPE_TITLE,
			);
			++$i;
		}
		$this->title_row = 1;
	}

	/**
	 * 将AR提供的数据转换为数组
	 * @param unknown_type $data
	 */
	private function _getDataProviderData($data){
		$titles = self::getTitles();
		$keys = array();
		if(isset($this->config['key'])){
			if(is_array($this->config['key'])){
				$keys = $this->config['key'];
			}else{
				$keys = array($this->config['key']);
			}
		}
		$this->keys_count = count($keys);
		$i = $this->title_row;
		$titles = array_merge($keys, $titles);
		foreach ($data as $d){
			$j = 0;
			foreach ($titles as $title){
				$t = $j<$this->keys_count ? self::$TYPE_KEY : self::$TYPE_STRING;
				if (preg_match('/\./', $title)){
					$td = $d;
					$cs = explode('.', $title);
					foreach($cs as $c){
						$td = $td->$c;
					}
					$this->data[$i][$j] = array(
							'v' => $td,
							't' => $t,
					);
				}else{
					$this->data[$i][$j] = array(
							'v' => $d->$title,
							't' => $t,
					);
				}
				++$j;
			}
			++$i;
		}
	}

	/**
	 * 格式化排序规则
	 * 正式的排序规则为：array(1=>SORT_ASC, 10=>ASC_DESC, ...)
	 * @param unknown_type $sort_cols 可以为一个列名，也可以为一个数组array('A'=>SORT_ASC, 'D'=>SORT_DESC, ...)
	 */
	private function _formatSortColumns($sort_cols){
		$sort_columns = array();
		if(is_string($sort_cols)){
			$col_num = self::getColumnNum($sort_cols) + $this->keys_count - 1;
			$sort_columns = array($col_num => SORT_ASC);
		}elseif(is_array($sort_cols)){
			foreach ($sort_cols as $c=>$t){
				$col_num = self::getColumnNum($c) + $this->keys_count - 1;
				$sort_columns[$col_num] = $t;
			}
		}
		return $sort_columns;
	}

	/**
	 * 将传入参数转换成标准的Grid数组
	 * @param unknown_type $data
	 */
	private function _toBaseGridArray($data, $default_type=null){
		if($default_type === null) $default_type = self::$TYPE_STRING;
		$new_data = array();
		if(is_array($data[0])){
			//如果是二维数组
			if(isset($data[0][0]['v'])){
				//如果已经是标准数组
				$new_data = $data;
			}else{
				foreach ($data as $i=>$row){
					foreach ($row as $j=>$cell){
						$new_data[$i][$j] = array(
							'v' => $cell,
							't' => $default_type
						);
					}
				}
			}
		}elseif(is_array($data)){
			// 如果是一维数组，将其转换成Grid标准数组
			foreach ($data as $i => $ld){
				$new_data[$i][0] = array(
					't' => self::$TYPE_STRING,
					'v' => $ld
				);
			}
		}
		return $new_data;
	}

	/**
	 * 向一个数据中插入元素
	 * @param array $array
	 * @param unknown_type $val
	 * @param int $pos
	 */
	protected function _insertArrayValue(&$array, $val, $pos){
		if(count($array)<$pos){
			$array[] = $val;
			return $array;
		}
		$fore = ($pos == 0) ? array() : array_splice($array, 0, $pos);
		$fore[] = $val;
		$array = array_merge($fore, $array);
		return $array;
	}

	/**
	 * 执行表达式
	 * @param unknown_type $col 执行的位置：列
	 * @param unknown_type $row 执行的位置：行
	 * @param unknown_type $val 表达式:%B . '　' . %C
	 * @param unknow $data 指定值
	 */
	private function _exec($col=null, $row=null, $val, $data=null){
		if($col!==null  && $row!==null && $data==null){
			$data = $this->getCellValue($col, $row);
		}
		$keys = $this->getRowKeys($row);
		if($keys!==null){
			$key = $keys[0];
		}
		$val = preg_replace_callback('/(%[A-Z]+)(\d*)/i', '_data_table_replace_column_name', $val);
		@eval("\$val = $val;");
		return $val;
	}
}

/**
 * 替换Excel中的变量，配合preg_replace_callback方法使用
 * @param unknown_type $column_match
 */
function _data_table_replace_column_name($column_match){
	$col = preg_replace('/%/', '', $column_match[1]);

	if(empty($column_match[2])){
		$row_str = '$row';
	}else{
		$row_str = intval($column_match[2]);
	}
	return '$this->data[' . $row_str . '+$this->title_row-1][self::getColumnNum(\'' . $col . '\')+$this->keys_count-1][\'v\']';
}

function _YDataGridSort($row1, $row2){

}
?>