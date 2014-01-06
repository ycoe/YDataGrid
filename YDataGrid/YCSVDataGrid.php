<?php
class YCSVDataGrid extends YDataGrid{
	/**
	 * 将数据写到CSV文件中，方便检查
	 * @param unknown_type $file_path CSV文件路径
	 * @param unknown_type $with_title 是否包含标题
	 */
	public function saveToCSV($file_path,$with_title=true){
		//创建文件
		if(!is_file($file_path)){
			//创建文件
				
		}
		$fh = fopen($file_path, 'w') or die('无法打开CSV文件：' . $file_path);
		$titles = $this->getTitles();
		if($with_title){
			if(fputcsv($fh, $titles) === false){
				die('无法写入CSV文件标题：' . $file_path);
			}
		}
		for($i=$this->title_row,$c=count($this->data);$i<$c;++$i){
			$row = $this->data[$i];
			$row_data = array();
			foreach ($row as $j=>$cell){
				$row_data[] = $cell['v'];
			}
			if(fputcsv($fh, $row_data) === false){
				die('无法写入CSV文件：' . $file_path);
			}
		}
		return $this;
	}
	
	/**
	 * 导出CSV文件
	 * @param string $nams csv文件名
	 */
	public function export($nams){
		$titles = $this->getTitles();
		$output = fopen('php://output', 'w') or die("Can't open php://output");
		fwrite($output,chr(0xEF).chr(0xBB).chr(0xBF)); //声明编码类型
		$total = 0;
		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="' . $nams . '.csv"');
		
		if(fputcsv($output, $titles) === false){
			die('无法写入CSV文件标题');
		}
		
		for($i=$this->title_row,$c=count($this->data);$i<$c;++$i){
			$row = $this->data[$i];
			$row_data = array();
			foreach ($row as $j=>$cell){
				$row_data[] = $cell['v'];
			}
			if(fputcsv($output, $row_data) === false){
				die('无法写入CSV文件');
			}
		}
		fclose($output) or die("Can't close php://output");
		exit();
	}
}

?>