<?php
/***************************************************************************
 * Copyright (c) 2021  The AppsMoto Authors
 * ZingFront.com, Inc. All Rights Reserved
 **************************************************************************/

/**
 * @file components/phpexcel/OutputExcel.php
 * @author caohy [caohongyuan@zingfront.com]
 * @date 2020-3-17 19:16:52
 * @brief
 **/

namespace app\components\phpexcel;

use Yii;
use yii\base\Component;

class OutputExcel extends Component
{
    /**
     * 导出Excel文件
     * @param $data
     * @param string $title
     * @param string $file_name
     * @return array
     */
    public function outputExcel($data, $title = '', $file_name = '')
    {
        require_once 'PHPExcel.php';
        $obj = new \PHPExcel();
        $obj->getProperties()
            ->setCreator("www.zingfront.com")
            ->setLastModifiedBy("www.zingfront.com");
        $objSheet = $obj->getActiveSheet();
        $objSheet->setTitle($title);
        # 格式化数据。
        $array = $this->formatExcelData($data);
        $objSheet->fromArray($array);
        # 参数写入
        $objWriter = \PHPExcel_IOFactory::createWriter($obj, 'Excel2007');
        $path = Yii::getAlias('@app/web/xls/');
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }
        $file_path = $path . $file_name;
        $objWriter->save($file_path);
        return [
            'file_path' => $file_path,
        ];
    }

    /**
     * 格式化数据
     * @param $data
     * @return mixed
     */
    public function formatExcelData($data)
    {
        $title = $data['title'];
        $data = $data['data'];
        array_unshift($data, $title);
        return $data;
    }

    /**
     * 绑定列
     * @param int $number
     * @param string $type
     * @return mixed|string
     */
    public function getColumnByNumber($number = 0, $type = 'upper')
    {
        $chr = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        if ($type === 'upper') {
            return $chr[$number];
        }
        return strtolower($chr[$number]);
    }


    public function actionOutputExcel()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;
        $product_id = $request->post('product_id');
        $columns = $request->post('columns');
        # 产品信息
        $product = Products::findOne($product_id);
        $this->product_id = $product->id;
        $this->product_mark = $product->product_mark;
        # 当前用户邮箱
        $user_email = Yii::$app->user->identity->email;
        $params = [];
        $title = [];
        # 格式化数据, 生成 title 和 data
        foreach ($columns as $key => $value) {
            if ($value['is_show'] == 'true') {
                $title[$value['value']] = $value['label'];
                $params[] = $value['value'];
            }
        }
        $request_columns = [];
        # 过滤需要导出的列
        foreach ($this->outputParams as $key => $value) {
            foreach ($value as $_key => $_value) {
                if (in_array($_value, $params)) {
                    $request_columns[$key][] = $_value;
                }
            }
        }
        # 目前仅支持近 90 天的用户
        $date = date("Y-m-d", strtotime("-90 day"));
        $users = User::find()
            ->select(['id'])
            ->where([
                'product_mark' => $this->product_mark,
                'is_deleted' => 0
            ])
            ->andWhere(['>=', 'created_at', $date])
            ->orderBy('id desc')
            ->asArray()
            ->all();
        $user_ids = array_chunk(array_column($users, 'id'), 500);
        $time_mark = date('md-His');
        # 压缩包
        $file_title = $product->name;
        $file_name_array = [
            $product->name,
            $user_email,
            $time_mark
        ];
        $path = Yii::getAlias('@app/web/xls/');
        $zip_name = implode($file_name_array, '-') . '.zip';
        $files_dom = [];
        $files_dom[] = $path . $zip_name;
        $zip = new \ZipArchive;
        $zip_status = $zip->open($path . $zip_name, \ZipArchive::CREATE);
        if (!$zip_status) {
            return ['code' => 109000, 'message' => '压缩异常'];
        }
        foreach ($user_ids as $key => $user_ids_sheet) {
            $get_data = [];
            foreach ($request_columns as $function => $columns) {
                $get_data[] = $this->$function($user_ids_sheet, $columns);
            }
            $return_data = $this->formatArrayMerge($get_data);
            $data = [
                'title' => $title,
                'data' => $return_data
            ];
            # 生成 Excel 文件
            $output_excel = new OutputExcel();
            # Excel 文件名称
            $file_name = ($key + 1) . '.xls';
            $res = $output_excel->outputExcel($data, $file_title, $file_name);
            $file_path = $res['file_path'];
            $files_dom[] = $file_path;
            $relative_path = '/zbase/' . $file_name;
            $zip->addFile($file_path, $relative_path);
        }
        $zip->close();
        # 把文件上传到 OSS
        $oss_client = new MiddlePlatformOssClient();
        $oss_file = 'zbase/xls/' . date('Y') . '/' . $product_id . '/' . $zip_name;
        $oss_client->uploadFile($oss_file, $path . $zip_name);
        $response_data = [
            'url' => $oss_client->cdn . '/' . $oss_file,
            'file_name' => $zip_name
        ];
        foreach ($files_dom as $key => $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        return [
            'code' => 100000,
            'message' => '上传成功',
            'data' => $response_data,
        ];
    }

}

