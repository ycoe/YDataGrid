<?php
class YExcelDataGrid extends YDataGrid{
	/**
	 * 生成Excel时，合并表单的配置，本配置是在运行过程中赋值的
	 * 本变量为一个二维数组，默认值为空，与表格的行、列一致
	 * 有丙种值：空值（正常）、false（不显示，直接跳过）
	 * @var unknown_type
	 */
	private static $MERGIN_CONFIGS = null;

	/**
	 * 导出Excel文件
	 * @param string $nams Excel文件名
	 */
	public function export($nams, $config=null){
		$config = array_merge(array(
			'creator' => '邻购（北京）电子商务有限公司',
			'lastModifiedBy' => 'Lingou-POS',
			'title' => '无标题',
			'subject' => '',
			'description' => '',
			'keywords' => 'lingou',
			'category' => 'YExcelDataGrid',
			'sheetName' => 'Sheet'
		), $config === null?array():$config);

		$maxStrLen = array();
		$objExcel = new PHPExcel();

		$dataBorderStyle = array(
			'style' => PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
			'color' => array ('rgb' => 'BBCBCF'),          	//设置border颜色
		);
		$defaultBorderStyle = array(
			'style' => PHPExcel_Style_Border::BORDER_THIN,   //设置border样式
			'color' => array ('rgb' => 'FFFFFF'),          //设置border颜色
		);

		//设置文档基本属性
		$objProps = $objExcel->getProperties();
		$objProps
			->setCreator($config['creator'])
			->setLastModifiedBy($config['lastModifiedBy'])
			->setTitle($config['title'])
			->setSubject($config['subject'])
			->setDescription($config['description'])
			->setKeywords($config['keywords'])
			->setCategory($config['category']);

		//缺省情况下，PHPExcel会自动创建第一个sheet被设置SheetIndex=0
		$objExcel->setActiveSheetIndex(0);
		$objActSheet = $objExcel->getActiveSheet();

		//默认对齐
		$objActSheet->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

		$objActSheet->getDefaultRowDimension()->setRowHeight(20);
		$objActSheet->getDefaultStyle()->applyFromArray(array(
			'borders' => array(
				'bottom' => $defaultBorderStyle,
				'right' => $defaultBorderStyle
			),
			'font' => array(
				'color' => array('rgb' => '4F6B72')
			)
		));

		$objActSheet->setTitle($config['sheetName']);

		$lastColName = 'A';

		//写入标题
		$titles = $this->getTitles();
		if(!empty($titles)){
			$col_index = 1;
			foreach($titles as $title){
				$colName = self::getColumnCode($col_index);
				$objActSheet->setCellValue($colName . '1', $title);

				$maxStrLen[$col_index-1] = strlen($title);

				$objActSheet->getStyle($colName . '1')->applyFromArray(array(
					'font' => array(
						'bold' => true,
						'name' => 'Trebuchet MS, Verdana, Arial, Helvetica, sans-serif',
						'color' => array('rgb' => '4F6B72')
					),
					'borders' => array(
						'left' => $dataBorderStyle,
						'top' => $dataBorderStyle,
						'right' => $dataBorderStyle,
						'bottom' => $dataBorderStyle,
					),
					'fill' => array(
						'type' => PHPExcel_Style_Fill::FILL_GRADIENT_LINEAR,
						'color' => array('rgb' => 'CAE8EA')
					)
				));
				++$col_index;
			}
			$lastColName = $colName;
			//设置标题样式
			$objActSheet->getStyle('A1:' . $lastColName . '1')
			->getFill()
			->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
			->getStartColor()->setRGB('CAE8EA');
		}

		//写入内容
		$MERGIN_CONFIGS = array();
		for($row_index = $this->title_row, $c = count($this->data); $row_index < $c; ++$row_index){
			$row = $this->data[$row_index];
			for($col_index = $this->keys_count, $count = count($row); $col_index < $count; ++$col_index){
				if(isset(self::$MERGIN_CONFIGS[$row_index][$col_index])){
					$mv = self::$MERGIN_CONFIGS[$row_index][$col_index];
					if($mv === false){
						//跳过
						continue;
					}
				}
				$cell = $row[$col_index];
				$merger = false;
				$colspan = 1;
				if(isset($cell['colspan'])){
					//如果有设置合并
					$colspan = intval($cell['colspan']);
					if($colspan > 1){
						$merger = true;
						//写入配置
						for($j=1;$j<$colspan;++$j){
							self::$MERGIN_CONFIGS[$row_index][$col_index + $j] =false;
						}
					}
				}
				$rowpan = 1;
				if(isset($cell['rowspan'])){
					//如果有设置合并
					$rowpan = intval($cell['rowspan']);
					if($rowpan > 1){
						$merger = true;
						//写入配置
						for($j=1;$j<$rowpan;++$j){
							self::$MERGIN_CONFIGS[$row_index + $j][$col_index] =false;
						}
					}
				}

				$v = (isset($cell['v']) ? strip_tags($cell['v']) : '');
// 				if($cell['t'] == self::$TYPE_DOUBLE){
// 					$v = number_format($v, 2);
// 				}

				$strLen = strlen($v);
				if($strLen > $maxStrLen[$col_index - $this->keys_count]){
					$maxStrLen[$col_index - $this->keys_count] = $strLen;
				}

				$colName = self::getColumnCode($col_index - $this->keys_count + 1);
				$cellStr = $colName . ($row_index + $this->title_row);
//				$objActSheet->setCellValue($cellStr, $v);
				// 所有写入数据类型都当成文本(避免科学计数法方式显示的出现)
				$objActSheet->setCellValueExplicit($cellStr, $v, PHPExcel_Cell_DataType::TYPE_STRING);

				$borders = $objActSheet->getStyle($cellStr)->getBorders();
				$this->_setCellBorder($borders, $dataBorderStyle);

				if(isset($cell['class'])){
					$clazz = $cell['class'];
					$color = null;
					if(strstr($clazz, 'title')!==false){
						$color = 'E4F1F1';
					}elseif(strstr($clazz, 'alt')!==false){
						$color = 'D3E8E8';
					}
					if($color !== null){
						$objActSheet->getStyle($cellStr)
						->getFill()
						->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
						->getStartColor()->setRGB($color);
					}
				}

				//合并单元格
				if($merger){
					$endColName = self::getColumnCode($col_index - $this->keys_count + $colspan);
					$endRow = $row_index + $rowpan;
					$objActSheet->mergeCells($colName . ($row_index + $this->title_row) . ':' . $endColName . $endRow);
				}
			}
		}

		foreach ($maxStrLen as $col_index=>$len){
			$len += 3;
			if($len > 32)
				$len = 32;
			$colName = self::getColumnCode($col_index + 1);
			$objActSheet->getColumnDimension($colName)->setWidth($len);
		}

		$excel_type = 'Excel5';
		$ext = 'xls';
		if(isset($config['excel_type']) && $config['excel_type'] == 'Excel2007'){
			$excel_type = 'Excel2007';
			$ext = 'xlsx';
		}

		ob_clean();	//清空之前的输出
		header('Content-Type:  application/force-download');
		header('Content-Disposition: attachment;filename="' . $nams . '.' . $ext . '"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objExcel, $excel_type);
		$objWriter->save('php://output');
		exit;
	}

	private function _setCellBorder($borders, $borderStyle){
		//设置全局边框样式
		$borders->getBottom()->applyFromArray($borderStyle);
		$borders->getRight()->applyFromArray($borderStyle);
		$borders->getLeft()->applyFromArray($borderStyle);
		$borders->getTop()->applyFromArray($borderStyle);
	}
}

?>