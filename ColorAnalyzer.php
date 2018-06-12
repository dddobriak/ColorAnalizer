<?php

class ColorAnalyser
{

	const UPLOAD_DIR = __DIR__ . '/images/';
	private static $fileType;
	private static $fileSize;
	private static $fileName;
	private static $fileTempName;
	private static $imageQuanity;
	
	private function __construct() {}
	private function __clone() {}
	private function __wakeup() {}

	/*
	* uploadCheck
	* @return void
	*/
	private static function uploadCheck()
	{
		if (isset($_FILES['image_upload'])) {
			self::$fileType = $_FILES['image_upload']['type'];
			self::$fileSize = $_FILES['image_upload']['size'];
			self::$fileName = $_FILES['image_upload']['name'];
			self::$fileTempName = $_FILES['image_upload']['tmp_name'];
			self::$imageQuanity = (int)$_POST['image_quantity'];
		} else {
			throw new Exception('$_FILES пуст');
		}
	}

	/*
	* uploadImage
	* @return string
	*/
	private static function uploadImage()
	{
		try {
			self::uploadCheck();
		} catch (Exception $e) {
			throw new Exception('Изображение не загружено т.к. uploadCheck передал: ' . $e->getMessage());
		}

		$uploadFile = false;
		$fileData = [];

		$uploadFile = self::UPLOAD_DIR . basename(self::$fileName);

		if (!empty(self::$fileTempName)) {
			move_uploaded_file(self::$fileTempName, $uploadFile);
			return $uploadFile;
		} else {
			throw new Exception('Изображение не загружено т.к. tmp_name пуст');
		}
	}

	/*
	* imageCalc
	* @return array
	*/
	private static function imageCalc()
	{
		try {
			self::uploadImage();
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}

		$image_resource = false;

		if (self::$fileType === 'image/jpeg') {
			$image_resource = imagecreatefromjpeg(self::uploadImage());
		} else if (self::$fileType === 'image/png') {
			$image_resource = imagecreatefrompng(self::uploadImage());
		} else {
			throw new Exception('Загружать можно только jpeg или png');
		}

		$width = imagesx($image_resource);
		$height = imagesy($image_resource);
		$color = [];

		// Анализ изображения, запись цветов каждого пикселя массив $color[]
		for ($x = 0; $x < $width; $x++) {
			for ($y = 0; $y < $height; $y++) {
				$color[] = imagecolorat($image_resource, $x, $y);
			}
		}

		// Удаляем ключи, сортируем (после очистки ключей скрипт работает немного быстрее, почему - вопрос)
		$arrayColors = array_values($color);
		arsort($arrayColors);

		$arrayGroup = [];
		$groupIndex = 0;
		$resultIndex = 0;
		$arrayResult = [];

		// Группируем повторяющиеся значения для последующего их подсчета
		foreach ($arrayColors as $color) {
			if ($color === $arrayGroup[$groupIndex][0]) {
				$arrayGroup[$groupIndex][] = $color;
			} else {
				$groupIndex++;
				$arrayGroup[$groupIndex][] = $color;
			}
		}

		foreach ($arrayGroup as $value) {
			$arrayResult[$resultIndex]['color'] = $value[0];
			$arrayResult[$resultIndex]['quantity'] = count($value);
			$resultIndex++;
		}

		usort($arrayResult, function($a, $b) {
			if ($a['quantity'] == $b['quantity']) {
				return 0;
			}
			return ($a['quantity'] > $b['quantity']) ? -1 : 1;
		});

		// Кол-во палитр (эксперементальная вещь, порой выдает неинтересные похожие результаты, необходима регулировка диапазона)
		if (self::$imageQuanity > 0) {
			$imageCalcData = [];
			for ($i = 0; $i < self::$imageQuanity; $i++) {
				$imageCalcData[] = imagecolorsforindex($image_resource, $arrayResult[$i]['color']);
			}
		} else {
			$imageCalcData = imagecolorsforindex($image_resource, $arrayResult[0]['color']);
		}

		imagedestroy($image_resource);
		return $imageCalcData;
	}

	/*
	* getResult
	* @return void
	*/
	static function getResult()
	{
		try {
			self::imageCalc();
		} catch (Exception $e) {

			//echo $e->getMessage();
			return false;
		}

		ob_start();
		if (isset(self::imageCalc()[0])) {
			foreach(self::imageCalc() as $item) {
				$alpha = ($item['alpha'] > 1) ? '0' : '1';
				$rgba = "{$item['red']}, {$item['green']}, {$item['blue']}, {$alpha}";
				?>
				<div class="colortest rounded mt-2 mb-2" style="height: 25px; border: 1px dashed #ddd; background: rgba(<?php echo $rgba; ?>);"></div>
				<?php
			}
		} else {
				$item = self::imageCalc();
				$alpha = ($item['alpha'] > 1) ? '0' : '1';
				$rgba = "{$item['red']}, {$item['green']}, {$item['blue']}, {$alpha}";
				?>
				<div class="colortest rounded mt-2 mb-2" style="height: 25px; border: 1px dashed #ddd; background: rgba(<?php echo $rgba; ?>);"></div>
				<?php
		}
		return ob_get_clean();
	}
}