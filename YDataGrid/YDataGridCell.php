<?php
/**
 * 表格基类，用于动态设置表格时使用
 * @author ycoe
 *
 */
abstract class YDataGridCell{
	/**
	 * 整个YDataGrid
	 * @var YDataGrid
	 */
	public $grid;
	
	/**
	 * 当前列的key
	 * @var unknown
	 */
	public $key;
	
	/**
	 * 当前列的keys
	 * @var unknown
	 */
	public $keys;
	
	/**
	 * 当前单元格的值
	 * @var unknown
	 */
	public $cell_data;
	
	/**
	 * 行索引，包含title
	 * @var unknown
	 */
	public $row_index;
	
	/**
	 * 列索引，包含keys
	 * @var unknown
	 */
	public $col_index;
	
	/**
	 * 用于判断当前是否有效
	 * @return boolean
	 */
	public function v(){
		return true;
	}
	
	/**
	 * 渲染，默认将返回此方法渲染的内容 
	 */
	public abstract function render();
	
	/**
	 * 当v()验证失败时渲染的内容，需要时，可以重写
	 * @return NULL
	 */
	public function renderFalseV(){
		return null;
	}
	
	/**
	 * 
	 * @return NULL
	 */
	public final function toString(){
		if($this->v()){
			return $this->render();
		}else{
			return $this->renderFalseV();
		}
	}
}
?>