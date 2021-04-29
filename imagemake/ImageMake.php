<?php


class ImageMake
{

    public function outputImage($config)
    {
        $file_name = $config['file_name'];

        $this->createText($config);

        imagepng($this->createImage(), $file_name);
    }

    # 单纯创建文本图片
    # 参数设置：
    #
    public function createText($config)
    {
        $bg_config = [
            'width' => 320,
            'height' => 40,
        ];
        $image = $this->createImage($bg_config['width'], $bg_config['height']);
        $black = imagecolorallocate($image, 0, 0, 0);
        $config = [
            'size' => 20, # 大小
            'angle' => 0, # 角度0-90
            'x' => 10, # top
            'y' => 10 + 20, # left
            'color' => $black, # 颜色
//            'fontfile' => 'imagemake/src/fonts/Arial.ttf', # 字体
            'fontfile' => 'imagemake/src/fonts/STHeiti Light.ttc', # 字体
            'text' => 'YTCC-000中文', # 文本内容
        ];
        $image_box = imagettfbbox($config['size'], $config['angle'], $config['fontfile'], $config['text']);
        # 计算文本的实际大小
        $text_length = $image_box[2] - $image_box[0];
        $config['x'] = ($bg_config['width'] - $text_length) / 2;
        imagettftext($image, $config['size'], $config['angle'], $config['x'], $config['y'], $config['color'], $config['fontfile'], $config['text']);
        $image_path = 'img/text.png';
        imagepng($image, $image_path);
        imagedestroy($image);
        $images = [
            'merge/15.jpeg',
            $image_path,
        ];
        return $this->imageMerge($images, 'img/merge.png', true);
    }

    public function getImageInfo($image_path)
    {
        $images = [
            'merge/1.png',
            'img/qrcode.png',
            'merge/3.png',
            'merge/4.png',
        ];
        return $this->imageMerge($images, 'img/merge.png', true);
    }

    /**
     * 合并图片
     * @param $images --图片集合，里面是图片地址
     * @param $output --输出文件地址和名称（完整）
     * @param bool $direction 方向: true=纵向合并,false=横向合并
     * @return mixed
     */
    public function imageMerge($images, $output, $direction = false)
    {
        foreach ($images as $key => $image) {
            list($width, $height) = getimagesize($image);
            $images[$key] = [
                'path' => $image,
                'width' => $width,
                'height' => $height,
            ];
        }
        # 根据方向，计算尺寸
        # 纵向：宽：最宽的图片，高：计算和
        # 横向：宽：计算和，高：最高的图片
        $image_width = 0;
        $image_height = 0;
        if ($direction) {
            foreach ($images as $key => $image) {
                $image_width = $image['width'] > $image_width ? $image['width'] : $image_width;
                $images[$key]['site'] = [
                    'dst_x' => 0,
                    'dst_y' => $image_height,
                    'src_x' => 0,
                    'src_y' => 0,
                    'src_w' => $image['width'],
                    'src_h' => $image['height'],
                ];
                $image_height += $image['height'];
            }
        } else {
            foreach ($images as $key => $image) {
                $image_height = $image['height'] > $image_height ? $image['height'] : $image_height;
                $images[$key]['site'] = [
                    'dst_x' => $image_width,
                    'dst_y' => 0,
                    'src_x' => 0,
                    'src_y' => 0,
                    'src_w' => $image['width'],
                    'src_h' => $image['height'],
                ];
                $image_width += $image['width'];
            }
        }
        # 根据尺寸，创建对应的真彩色图像背景
        $merged_image = imagecreatetruecolor($image_width, $image_height);
        imagealphablending($merged_image, false);
        //保留透明颜色
        imagesavealpha($merged_image, true);

        //创建透明背景色，主要127参数，其他可以0-255，因为任何颜色的透明都是透明
        $transparent = imagecolorallocatealpha($merged_image, 0, 0, 0, 127);
        //指定颜色为透明
        imagecolortransparent($merged_image, $transparent);
        //保留透明颜色
        imagesavealpha($merged_image, true);
        //填充图片颜色
        imagefill($merged_image, 0, 0, $transparent);


        foreach ($images as $key => $image) {
            $src_im = imagecreatefrompng($image['path']);
//            int $dst_x , int $dst_y , int $src_x , int $src_y , int $src_w , int $src_h
//            将 src_im 图像中坐标从 src_x，src_y 开始，宽度为 src_w，高度为 src_h 的一部分拷贝到 dst_im 图像中坐标为 dst_x 和 dst_y 的位置上。
            imagecopy(
                $merged_image, $src_im,
                $image['site']['dst_x'], $image['site']['dst_y'],
                $image['site']['src_x'], $image['site']['src_y'],
                $image['site']['src_w'], $image['site']['src_h']
            );
//            dst_image 目标图象资源。
//            src_image 源图象资源。
//            dst_x 目标 X 坐标点。
//            dst_y 目标 Y 坐标点。
//            src_x 源的 X 坐标点。
//            src_y 源的 Y 坐标点。
//            dst_w 目标宽度。
//            dst_h 目标高度。
//            src_w 源图象的宽度。
//            src_h 源图象的高度。
//            提高清晰度
//            imagecopyresampled($merged_image, $temp_image, 0, 0, 0, 0, $image['width'], $image['height'], $image['width'], $image['height']);
        }
        imagepng($merged_image, $output);
        imagedestroy($merged_image);

        return $output;
    }

    /**
     * 创建画布
     * @param int $width
     * @param int $height
     * @param string $color
     * @return false|resource
     */
    public function createImage($width = 300, $height = 400, $color = '#ffffff')
    {
        //创建画布
        $image = imagecreatetruecolor($width, $height);
        //填充画布背景色
        $color_rgb = $this->hex2rgb($color);
        $color = imagecolorallocate($image, $color_rgb['r'], $color_rgb['g'], $color_rgb['b']);
        imagefill($image, 0, 0, $color);
        return $image;
    }

    /**
     * 十六进制 转 RGBA
     * @param string $hex_color 颜色16进制值，如fff或#ffffff
     * @return array
     */
    public function hex2rgb($hex_color = '#ffffff')
    {
        $color = str_replace('#', '', $hex_color);
        if (strlen($color) > 3) {
            $rgb = array(
                'r' => hexdec(substr($color, 0, 2)),
                'g' => hexdec(substr($color, 2, 2)),
                'b' => hexdec(substr($color, 4, 2))
            );
        } else {
            $color = $hex_color;
            $r = substr($color, 0, 1) . substr($color, 0, 1);
            $g = substr($color, 1, 1) . substr($color, 1, 1);
            $b = substr($color, 2, 1) . substr($color, 2, 1);
            $rgb = array(
                'r' => hexdec($r),
                'g' => hexdec($g),
                'b' => hexdec($b)
            );
        }
        return $rgb;
    }

}