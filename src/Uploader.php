<?php
namespace RedCat\FileIO;
class Uploader{
	public $extensionRewrite = [
		'jpeg'=>'jpg',
	];
	function image($conf,$multi=false){
		$conf = $conf+[
			'dir'=>'',
			'key'=>'image',
			'rename'=>false,
			'width'=>false,
			'height'=>false,
			'multi'=>false,
			'extensions'=>Image::$extensions,
			'conversion'=>null,
		];
		extract($conf);
		$func = 'file'.($multi?'s':'');
		$dir = rtrim($dir,'/').'/';
		return $this->$func($dir,$key,'image/',function($file)use($width,$height,$rename,$conversion){
			$ext = strtolower(pathinfo($file,PATHINFO_EXTENSION));
			if($conversion&&$ext!=$conversion&&($imgFormat=exif_imagetype($file))!=constant('IMAGETYPE_'.strtoupper($conversion))){
				switch($imgFormat){
					case IMAGETYPE_GIF :
						$img = imagecreatefromgif($file);
					break;
					case IMAGETYPE_JPEG :
						$img = imagecreatefromjpeg($file);
					break;
					case IMAGETYPE_PNG :
						$img = imagecreatefrompng($file);
					break;
					default:
						throw new UploadException('image format conversion not supported');
					break;
				}
				$oldFile = $file;
				$file = substr($file,0,-1*strlen($ext)).$conversion;
				$ext = $conversion;
				$convertF = 'image'.$conversion;
				$convertF($img, $file);
				unlink($oldFile);
			}
			if($rename){
				if($rename===true)
					$rename = 'image';
				$rename = $this->formatFilename($rename);
				rename($file,dirname($file).'/'.$rename.'.'.$ext);
			}
			if(($width||$height)&&in_array($ext,Image::$extensions_resizable)){
				$thumb = dirname($file).'/'.pathinfo($file,PATHINFO_FILENAME).'.'.$width.'x'.$height.'.'.$ext;
				Image::createThumb($file,$thumb,$width,$height,100,true);
			}
		},function($file)use($extensions){
			$ext = strtolower(pathinfo($file,PATHINFO_EXTENSION));
			if(!in_array($ext,(array)$extensions))
				throw new UploadException('extension');
		});
	}
	function formatFilename($name){
		$name = filter_var(str_replace([' ','_',',','?'],'-',$name),FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$e = strtolower(pathinfo($name,PATHINFO_EXTENSION));
		if(isset($this->extensionRewrite[$e]))
			$name = substr($name,0,-1*strlen($e)).$this->extensionRewrite[$e];
		return $name;
	}
	function uploadFile(&$file,$dir='',$mime=null,$callback=null,$precallback=null,$nooverw=null,$maxFileSize=null){
		if($file['error']!==UPLOAD_ERR_OK)
			throw new UploadException($file['error']);
		if($mime&&stripos($file['type'],$mime)!==0)
			throw new UploadException('type');
		if($maxFileSize&&filesize($file['tmp_name'])>$maxFileSize)
			throw new UploadException(UPLOAD_ERR_FORM_SIZE);
		@mkdir($dir,0777,true);
		$name = $this->formatFilename($file['name']);
		if($nooverw){
			$i = 2;
			while(is_file($dir.$name))
				$name = pathinfo($name,PATHINFO_FILENAME).'-'.$i.'.'.pathinfo($name,PATHINFO_EXTENSION);
			$i++;
		}
		if($precallback)
			$precallback($dir.$name);
		if(!move_uploaded_file($file['tmp_name'],$dir.$name))
			throw new UploadException('move_uploaded_file');
		if($callback)
			$callback($dir.$name);
		return $name;
	}
	function file($dir,$k,$mime=null,$callback=null,$precallback=null,$maxFileSize=null){
		if(isset($_FILES[$k])){
			if($_FILES[$k]['name'])
				return $this->uploadFile($_FILES[$k],$dir,$mime,$callback,$precallback,true,$maxFileSize);
		}
	}
	function files($dir,$k,$mime=null,$callback=null,$precallback=null,$maxFileSize=null){
		$returnFiles = [];
		if(isset($_FILES[$k])){
			$files =& $_FILES[$k];
			if(!is_array($files['name'])){
				$returnFiles[] = $this->file($dir,$k,$mime,$callback,$precallback,$maxFileSize);
			}
			else{
				for($i=0;count($files['name'])>$i;$i++){
					$file = [];
					foreach(array_keys($files) as $prop){
						$file[$prop] =& $files[$prop][$i];
					}
					if($file['name']){
						$returnFiles[] = $this->uploadFile($file,$dir,$mime,$callback,$precallback,false,true,$maxFileSize);
					}
				}
			}
			return $returnFiles;
		}
	}
}