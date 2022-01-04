<?php
namespace Simply_Static;

class Data_Flattener{
	public static function get_dummy_url(){
		return self::get_dummy_scheme().self::get_dummy_host();
	}
	public static function get_dummy_scheme(){
		return 'tmp://';
	}
	public static function get_dummy_host(){
		return 'dummy.example.com';
	}
	public static function get_files_to_replace_url($dir){
		$files=[];
		$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
		foreach($iterator as $info){
			$file=$info->getPathname();
			if(substr(basename($file),0,1)==='.'){continue;}
			if(in_array(strrchr($file,'.'),['.html','.css','.js'],true)){
				$files[]=$file;
			}
		}
		return $files;
	}
	public static function get_url_to_replace($dir,$moved_files){
		$urls=[];
		$len=strlen($dir)-1;
		foreach($moved_files as $to=>$from){
			$urls[substr($from,$len)]=substr($to,$len);
		}
		return $urls;
	}
	public static function get_relative_path($from,$to){
		$from_path=explode('/',dirname($from));
		$to_path=explode('/',$to);
		while($from_path[0]===$to_path[0]){
			array_shift($from_path);
			array_shift($to_path);
		}
		return str_repeat('../',count($from_path)).implode('/',$to_path);
	}
	public static function get_files_to_move($dir){
		$files=[];
		$conflicted_assets=[];
		foreach(['wp-includes','wp-content'] as $dirname){
			$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir.$dirname));
			foreach($iterator as $info){
				$from=$info->getPathname();
				if(substr(basename($from),0,1)==='.'){continue;}
				$to=$dir.'assets/'.self::get_assets_folder_name_for_file($from).'/'.basename($from);
				$files[$to][]=$from;
			}
		}
		foreach($files as $to=>$froms){
			if(count($froms)>1){
				foreach($froms as $from){
					$files[dirname($to).'/'.basename(dirname($from)).'-'.basename($to)]=$from;
				}
				unset($files[$to]);
			}
			else{
				$files[$to]=$froms[0];
			}
		}
		return $files;
	}
	public static function get_assets_folder_name_for_file($file){
		$ext=substr(strrchr($file,'.'),1);
		switch($ext){
			case 'jpg':
			case 'gif':
			case 'png':
			case 'webp':
				return 'img';
			case 'mp4':
			case 'webm':
				return 'mov';
			case 'woff':
			case 'woff2':
			case 'ttf':
			case 'otf':
				return 'fonts';
			default:
				return $ext;
		}
	}
}