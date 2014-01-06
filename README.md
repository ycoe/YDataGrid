本扩展是我在一个项目里面大量使用的，感觉用起来还比较方便，就把它分享上来了，除了YModelDataGrid类里面的quickParseXXX()方法外，其它功能都是通用的


功能：
简化表格操作、展示代码（可替代CGridView），支持:
1. %A * %C这样的操作（A列乘以C列）此类操作
2. 增加列、删除列、交换列、排序、合并单元格
3. 设置样式、设置表格数据（使用类）
4. Excel/CVS导出
5. 跟AR快速关联、关联类直接获取...
6. ....



安装：
1、下载附件，解压，将YDataGrid目录复制到{$app_dir}/protected/extensions/下，将css文件复制到网站的css目录
2、yii配置import中添加：application.extensions.YDataGrid.*
3、在页面上引入css文件（用于渲染表格样式，你也可以自己写）


使用：
class StoreNameCell extends YDataGridCell{
    public function render() {
        return Store::getName($this->cell_data); //$this->cell_data表示当前单元格的值
    }
}

//$order_list可以是AR数组，也可以是个数组！
$table = new YDataGrid($order_list, array(
    'columns' => array( //字义列
        'code',
        'name',
        'price',
        'count',
        'store_id',
        'user.name'  //如果此表与user表作了关联，可以直接使用这种方法直接获取到user表的name
    ),
    'key' => array('id', 'status') //指定keys，如果只有一个值时，也可以使用字符
));

$table
->setTitles(array('编码', '名称', '单价', '数量', '门店名称', '操作者')) //设置标题
->render() //可以在每一步都render出来，方便调试
->insertCol(0, 'exp:number_format(%C*%D, 2)', '小计') //在最后插入一列，并将结果保留2位小数
//->render()
->setColumnValues(
    'C' => 'php:number_format($data, 2)', //将第3列数字格式化
    'E' => new StoreNameCell(), //也可以写成：'php:Store::getName($data)'
)
->render();


说明：

列名A/B/C...与Excel类似，A表示第一列，0表示最后一列后，-1列表倒数第一列
%B表示第2列
%B3表示第3行第2列的一个单元格
B:D表示从第二列到第四列
2:4表示第2行到第四行
AB表示第27列

在所有可设置列值、表格值的地方，都可以使用：
exp：可以使用php和带%的YDataGrid表达式，也可以使用$data（表示当前单元格）
php：可用变量$data（表示当前单元格）、$key（初始化时设定的key的第一个值）、$keys[i]（初始化时设定的key的第i个值）
Class<? extends YDataGridCell>：扩展了YDataGridCell的类实例（这里使用java的写法比较好表达）

使用YExcelDataGrid/YCSVDataGrid时，需要三方扩展：phpexcel


示例：

test.pos.lingou.com (发不了链接，汗~~~)
测试用户名：r 
密码：123456
