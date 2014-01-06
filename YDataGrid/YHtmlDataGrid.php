<?php
class YHtmlDataGrid extends YDataGrid{
	/**
	 * 生成HTML时，合并表单的配置，本配置是在运行过程中赋值的
	 * 本变量为一个二维数组，默认值为空，与表格的行、列一致
	 * 有丙种值：空值（正常）、false（不显示，直接跳过）
	 * @var unknown_type
	 */
	private static $MERGIN_CONFIGS = null;
	
	/**
	 * 当前URL
	 * @var string
	 */
	private $url = null;
	
	/**
	 * 输出HTML
	 * @param boolean $debug 是否调试（调试状态下，会产生行号及列序号）
	 */
	public function render($debug=false){
		echo self::getHtml($debug);
		return $this;
	}
	
	public function setStyle($region, $style){
		$regions = $this->getCellByCode($region);
		foreach($regions as $reg){
			$row = $reg[0] + $this->title_row - 1;
			$col = $reg[1] + $this->keys_count - 1;
			$this->data[$row][$col]['style'] = $style;
		}
		return $this;
	}
	
	public function addStyle($region, $style){
		$regions = $this->getCellByCode($region);
		foreach($regions as $reg){
			$row = $reg[0] + $this->title_row - 1;
			$col = $reg[1] + $this->keys_count - 1;
			if(isset($this->data[$row][$col]['styles'])){
				//如果设置有样式，则进行合并
				$this->data[$row][$col]['style'] .= $style;
			}else{
				$this->data[$row][$col]['style'] = $style;
			}
		}
		return $this;
	}
	
	/**
	 * 生成HTML代码
	 * @param boolean $debug 是否调试（调试状态下，会产生行号及列序号）
	 */
	public function getHtml($debug=false){
		self::$MERGIN_CONFIGS = array();
		$this->url = $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
		$html = '';
		$html .= self::_renderCaption($debug);
		if($this->data !== null && count($this->data) > 0){
			$has_data = false;
			for($i=0, $c=count($this->data); $i < $c; ++$i){
				if($i < $this->title_row){
					$html .= self::_renderTitle($i, $this->data[$i], $debug);
				}else{
					$html .= self::_renderRow($i, $this->data[$i], $debug);
					$has_data = true;
				}
			}
			if(!$has_data && $this->title_row > 0){
				$title_count = count($this->data[0]);
				if($debug) ++$title_count;
				$td = CHtml::tag('td', array('colspan'=>$title_count), '暂无数据！');
				$html .= CHtml::tag('tr', array(), $td);
			}
		}else{
			$html .= '<tr><td>暂无数据！</td></tr>';
		}
		$table_attrs = isset($this->table_attr['htmlOptions'])?$this->table_attr['htmlOptions']:array();
		if(array_key_exists('class', $table_attrs)){
			$table_attrs['class'] .= ' y_table'; 
		}else{
			$table_attrs['class'] = 'y_table';
		}
		if(!empty($this->id)){
			$table_attrs['id'] = $this->id;
		}
		return CHtml::tag('table', $table_attrs, $html);
	}
	
	private function _getSortUrl($sort_col, $sort_dir){
		$url = $_SERVER['REQUEST_URI'];
		if(!preg_match('/\?/', $url)){
			$url .= '?';
		}
		if (preg_match('/^(.*)(&|\?)sort=([^&]*)(.*)$/', $url)) {
			$url = preg_replace('/^(.*)(&|\?)sort=([^&]*)(.*)$/', '$1$2sort=' . $sort_col . '$4', $url);
		}else{
			$url .= '&sort=' . $sort_col;
		}
		if (preg_match('/^(.*)(&|\?)sort_dir=([^&]*)(.*)$/', $url)) {
			$url = preg_replace('/^(.*)(&|\?)sort_dir=([^&]*)(.*)$/', '$1$2sort_dir=' . $sort_dir . '$4', $url);
		}else{
			$url .= '&sort_dir=' . $sort_dir;
		}
		return $url;
	}
	
	/**
	 * 生成Table的Caption行HTML
	 * @param boolean $debug
	 */
	private function _renderCaption($debug=false){
		if(!empty($this->caption)){
			return CHtml::tag('caption', array(), $this->caption);
		}
		return '';
	}
	
	/**
	 * 生成Table的标题行Html
	 * @param int $row_index 当前属于第几行，从0开始计
	 * @param array $row 当前行数据
	 * @param boolean $debug 是否调试
	 */
	private function _renderTitle($row_index, $row, $debug=false){
		$html = '';
		if($debug){
			if($row_index === 0){
				$opts = array();
				if($this->title_row > 0){
					$opts['row_span'] = $this->title_row;
				}
				$html .= CHtml::tag('th', $opts);
			}
		}
		$cur_sort_col = isset($_REQUEST['sort'])?$_REQUEST['sort']:'';
		for($col_index = 0, $count=count($row); $col_index < $count; ++$col_index){
			$opts = array();//属性
			if(!isset($this->data[$row_index][$col_index]))continue;
			$cell = $this->data[$row_index][$col_index];
			$v = $cell['v'];
			$char = self::getColumnCode($col_index + 1);
			if($debug){
				$v = $char . ':' . $v;
			}
			if(isset($cell['class'])){
				$opts['class'] = $cell['class']; 
			}
			if(isset($cell['sort_able']) && $cell['sort_able']){
				//如果允许排序
				$opts['sort'] = $char;
				$order_by = 'asc';
				if(isset($cell['sort_column_name']) && $cell['sort_column_name']){
					//使用原始列名
					if(isset($cell['s']))
						$char = $cell['s'];
				}
				if($cur_sort_col == $char){
					//如果是当前列在排序
					if(isset($_REQUEST['sort_dir']) && $_REQUEST['sort_dir']=='asc'){
						$order_by = 'asc';
						$v .= ' <span class="red sort_dir">↑</span>';
					}else{
						$order_by = 'desc';
						$v .= ' <span class="red sort_dir">↓</span>';
					}
					$opts['sort_dir'] = $order_by;
				}
				$v = CHtml::link($v, $this->_getSortUrl($char, $order_by==='asc'?'desc':'asc')); 
			}
			$html .= CHtml::tag('th', $opts, $v);
		}
		return CHtml::tag('tr', array('class'=>'row'), $html);
	}
	
	/**
	 * 生成Table的数据行HTML
	 * @param int $row_index 当前行数，从0开始计，包含标题行
	 * @param array $row 当前行数据
	 * @param boolean $debug 是否进入调试状态
	 */
	private function _renderRow($row_index, $row, $debug=false){
		$html = '';
		if($debug){
			$html .= CHtml::tag('td', array('class'=>'alt'), $row_index - $this->title_row + 1);
		}
		$trOpts = array(
			'class'=>'row',
		);
		$cel_index = 0;
		$colspan = 1;
		foreach ($row as $cell){
			$opts = array();
			if(isset(self::$MERGIN_CONFIGS[$row_index][$cel_index])){
				$mv = self::$MERGIN_CONFIGS[$row_index][$cel_index];
				if($mv === false){
					//如果为一空值时，直接跳过
					++$cel_index;
					continue;
				}
// 				else if(is_array($mv)){
// 					//如果是一数组时
// 					if($mv[0] > 1){
// 						//rowspan
// 						$opts['rowspan'] = $mv[0];
// 					}
// 					if($mv[1] > 1){
// 						//colspan
// 						$opts['colspan'] = $mv[1];
// 					}
// 				}
			}
			if($cel_index < $this->keys_count){
				//Key值
				$trOpts['key_' . $cel_index] = $cell['v'];
				++$cel_index;
				continue;
			}
			if(isset($cell['class'])){
				$opts['class'] = $cell['class'];
			}
			if(isset($cell['style'])){
				$opts['style'] = $cell['style'];
			}
			if(isset($cell['colspan'])){
				//如果有设置合并
				$colspan = intval($cell['colspan']); 
				if($colspan > 1){
					$opts['colspan'] = $colspan;
					//写入配置
					for($j=1;$j<$colspan;++$j){
						self::$MERGIN_CONFIGS[$row_index][$cel_index + $j] =false;
					}
				}
			} 
			if(isset($cell['rowspan'])){
				//如果有设置合并
				$rowpan = intval($cell['rowspan']); 
				if($rowpan > 1){
					$opts['rowspan'] = $rowpan;
					//写入配置
					for($j=1;$j<$rowpan;++$j){
						self::$MERGIN_CONFIGS[$row_index + $j][$cel_index] =false;
					}
				}
			} 
			$v = (isset($cell['v']) ? $cell['v'] : '');
			if($cell['t'] == self::$TYPE_DOUBLE){
				$v = number_format($v, 2);
			}
			$html .= CHtml::tag('td', $opts, $v);
			++$cel_index;
			--$colspan;
		}
		return CHtml::tag('tr', $trOpts, $html);
	}
	
	/**
	 * 链接 
	 * @param string $text 链接文字，可以使用变量
	 * @param unknown_type $url 链接，可以使用变量
	 * @param unknown_type $opts
	 */
	public static function link($text, $url, $opts=array(), $phped=true){
		if(isset($opts['visible']) && !$opts['visible'])return null;
		$html = ($phped?'php:' : '') . '"<a ';
		$opts['href'] = $url;
		foreach ($opts as $k=>$v){
			$html .= $k . '=\'' . $v . '\' ';
		}
		$html .= '>' . $text . '</a>"';
		return $html;
	}
	
	public static function jsLink($text, $scriptMethod, $opts=array(), $phped=true){
		if(isset($opts['visible']) && !$opts['visible'])return null;
		$opts['onclick'] = $scriptMethod . '(\"$key\", this)';
		$opts['href'] = 'javascript:void(0);';
		$html = ($phped?'php:' : '') . '"<a ';
		foreach ($opts as $k=>$v){
			$html .= $k . '=\'' . $v . '\'';
		}
		$html .= '>' . $text . '</a>"';
		return $html;
	}
	
	public static function deleteLink($text, $script='del', $opts=array(), $phped=true){
		return self::jsLink($text, $script, $opts, $phped);
	}

	public static function caseLink($conditionText, $trueLink, $falseLink, $exp = 'php:'){
		if($trueLink === null){
			$trueLink = '""';
		}
		if($falseLink === null){
			$falseLink = '""';
		}
		if(is_array($trueLink)){
			$trueLink = implode('. "' . YDATA_GRID_SPLIT . '" . ', $trueLink);
		}
		$html = $exp . '(' . $conditionText . ')?' . $trueLink . ':' . $falseLink;
		return $html;
	}
}

?>