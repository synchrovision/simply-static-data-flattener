<?php
/**
 * Plugin Name:       Simply Static Data Flatterner 
 * Description:       Flattern file structure of data exported by Simply Static.
 * Version:           0.1.0
 * Author:            synchro vision
 * Author URI:        https://catpow.info
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       simply-static-data-flattener
 * Domain Path:       /languages
 */
namespace Simply_Static;

spl_autoload_register(function($class){
	if(file_exists($f=__DIR__.'/classes/'.str_replace('\\','/',$class).'.php')){include $f;}
});
add_action('plugins_loaded',function(){
	if(file_exists($mo_file=__DIR__.'/languages/'.determine_locale().'.mo')){load_textdomain('simply-static-data-flattener',$mo_file);}
});


/**
* before creating archive,
* set destination_url_type to absolute
* and remember was override it
*/
add_action('ss_after_setup_task',function(){
	$ss_options=Options::instance();
	$destination_url_type=$ss_options->get('destination_url_type');
	$ss_options->set('original_destination_url_type',$destination_url_type);
	if($destination_url_type==='absolute'){
		$ss_options->set('archive_name',basename(untrailingslashit($ss_options->get('destination_host'))));
	}
	else{
		$ss_options->set('original_destination_url_type',$destination_url_type);
		$ss_options->set('destination_url_type','absolute');
		$ss_options->set('destination_scheme',Data_Flattener::get_dummy_scheme());
		$ss_options->set('destination_host',Data_Flattener::get_dummy_host());
	}
});

/**
* on finished fetching pages,
* move and rename files in wp-content or wp-includes of archive directory,
* and replace url in css or html files
*/
add_action('ss_finished_fetching_pages',function(){
	$ss_options=Options::instance();
	$archive_dir=$ss_options->get_archive_dir();
	$files_to_move=Data_Flattener::get_files_to_move($archive_dir);
	foreach($files_to_move as $to=>$from){
		if(!is_dir(dirname($to))){mkdir(dirname($to),0755,true);}
		rename($from,$to);
	}
	exec(sprintf("rm -rf %s",escapeshellarg($archive_dir.'wp-content')));
	exec(sprintf("rm -rf %s",escapeshellarg($archive_dir.'wp-includes')));
	$files_to_replace_url=Data_Flattener::get_files_to_replace_url($archive_dir);
	$url_to_replace=Data_Flattener::get_url_to_replace($archive_dir,$files_to_move);
	$original_destination_url_type=$ss_options->get('original_destination_url_type');
	$dummy_url=Data_Flattener::get_dummy_url();
	do_action('ssdf_before_replace_dummy_url',$files_to_replace_url,$dummy_url,$url_to_replace,$original_destination_url_type);
	foreach($files_to_replace_url as $file){
		if(!file_exists($file)){continue;}
		$contents=strtr(file_get_contents($file),$url_to_replace);
		switch($original_destination_url_type){
			case 'offline':
				$replacements=[];
				$path=explode('/',substr($file,strlen($archive_dir)));
				$deps=count($path);
				array_unshift($path,$dummy_url);
				while(isset($path[1])){
					array_pop($path);
					$replacements[implode('/',$path).'/']=str_repeat('../',$deps-count($path));
					$replacements[str_replace('/','\\/',implode('/',$path).'/')]=str_repeat('..\\/',$deps-count($path));
				}
				$contents=strtr($contents,$replacements);
				break;
			case 'relative':
				$contents=str_replace([$dummy_url,str_replace('/','\\/',$dummy_url)],'',$contents);
				break;
		}
		file_put_contents($file,$contents);
	}
	$ss_options->set('destination_url_type',$original_destination_url_type);
});