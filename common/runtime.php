<?php
/**
 * Created by PhpStorm.
 * User: nemo
 * Date: 14-7-2
 * Time: 13:49
 *
 * 自动生成开发时的运行时JS
 */
error_reporting(0);

class FrontEndRuntime {

    public $loadedApps = array();

    private $whiteList = array("main.js", "config.json");

    private $preloadingList = array(
        "vendor/bower_components.min.js",
        "vendor/ace.min.js",
        "vendor/angular-file-upload.min.js",
        "vendor/highcharts.js",
        "vendor/highchartsExporting.js",
        "vendor/ng-highcharts.js",
        "base/config.js",
        "lib/function.js",
        "lib/formMaker.js",
        "lib/plugin.js",
        "base/controller.js",
        "base/filter.js",
        "base/directive.js",
        "base/service.js",
        "base/model.js",
        "base/app.js",
    );

    private $afterLoadingList = array(
        "lib/commonView.js",
    );

    private $blackList = array(
        ".",
        "..",
        ".DS_Store",
        ".jshintrc",
        "__MACOS",
        "app.js",
        "login.js",
        "config.js",
        "function.js",
        "formMaker.js",
        "commonView.js"
    );

    private $baseDir;

    private $included = array();

    public $unfoundApp = array();

    public function __construct() {
        $this->baseDir = dirname(__FILE__);
    }

    private function response($data) {
        echo $this->doTrim($data);
    }


    public function preloadJS() {
        $find = array(
            "'[ones.requirements.placeholder]'",
            "'[ones.loadedApps.placeholder]'"
        );
        $replace = array(
            sprintf("'%s'", implode("','", $this->loadedApps)),
            sprintf("'%s'", implode("','", $this->loadedApps)),
        );
        foreach($this->preloadingList as $pre) {
            $tmp = $this->baseDir."/".$pre;
            if(is_file($tmp)){
                $content = file_get_contents($tmp);
                $content = str_replace($find, $replace, $content);
                $this->response($content);
            }
        }
    }

    public function afterLoadJS() {
        foreach($this->afterLoadingList as $af) {
            $tmp = $this->baseDir."/".$af;
            if(is_file($tmp)){
                $content = file_get_contents($tmp);
//                $content = str_replace($find, $replace, $content);
                $this->response($content);
            }
        }
    }

    public function preloadI18n($lang) {
        $content = file_get_contents($this->baseDir."/i18n/".$lang.".json");
        return json_decode($content, true);
    }

    /*
     * 合并并返回APP i18n
     * **/
    public function combineI18n($dir=false, $langData = array(), $lang) {
        $dir = $dir ? $dir : dirname($this->baseDir)."/apps";
        if(!is_dir($dir)) {
            return;
        }
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                $tmpPath = $dir."/".$file;
                if(!is_dir($tmpPath) or !is_dir($tmpPath."/i18n")) {
                    continue;
                }
                $langFile = $tmpPath."/i18n/".$lang.".json";
                if(!is_file($langFile)) {
                    continue;
                }
                $tmpLang = json_decode(file_get_contents($langFile), true);
                if(is_array($tmpLang) && $tmpLang) {
                    $langData = array_merge_recursive($langData, $tmpLang);
                }
            }
            closedir($dh);
        }

        return $langData;
    }

    public function combineJS($dir=false, $require=false) {
        $dir = $dir ? $dir : dirname($this->baseDir)."/apps";

        if($require && !is_dir($dir)) {
            $this->unfoundApp[] = end(explode("/", $dir));
            return;
        }

        if(!is_dir($dir)) {
            return;
        }
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if((!is_dir($dir.'/'.$file) && !in_array($file, $this->whiteList)) or in_array($file, $this->blackList)) {
                    continue;
                }
                $tmpPath = $dir."/".$file;
                $tmpConfigPath = $dir."/config.json";
                if((is_dir($tmpPath))) {
                    $this->combineJS($tmpPath);
                } else {
                    //配置文件
                    if(is_file($tmpConfigPath) && !in_array($tmpConfigPath, $this->included)) {
                        $this->included[md5($tmpConfigPath)] = $tmpConfigPath;
                        $tmpConfig = json_decode(file_get_contents($dir."/config.json"), true);
                        //优先加载依赖文件
                        if($tmpConfig["requirements"]) {
                            foreach($tmpConfig["requirements"] as $dep) {
                                if(array_key_exists($dep, $this->loadedApps)) {
                                    continue;
                                }
                                $depPath = dirname($dir)."/".$dep;
                                $this->combineJS($depPath, true);
                            }
                        }
                        if($tmpConfig["alias"] && is_file($dir."/main.js")) {
                            $this->loadedApps[$tmpConfig["alias"]] = "ones.".$tmpConfig["alias"];
                        }
                    } else {
                        if(!in_array($tmpPath, $this->included)) {
                            if(end(explode(".", $tmpPath)) === "js") {
                                $this->response(file_get_contents($tmpPath));
                            }

                            $this->included[md5($tmpPath)] = $tmpPath;

                        }
                    }
                }
            }
            closedir($dh);
        }

        return $this->loadedApps;
    }

    public function doTrim($code) {
        $code = str_replace(array(
            "    ", "'use strict';", "'use strict'",
            "\t",
        ), "", $code);
        return $code;
    }

    public function parseConfig() {}

    public function setWhiteList() {}

    public function debug() {
        print_r($this->included);
    }

}

$runtime = new FrontEndRuntime();

switch($_GET["action"]) {
    case "getI18n":
        header("Content-Type:application/json;charset=utf-8");
        $data = $runtime->preloadI18n($_GET["lang"]);
        $data = $runtime->combineI18n(false, $data, $_GET["lang"]);
        echo json_encode($data);
        break;
    default:
        header("Content-Type:application/javascript;charset=utf-8");
        ob_start();
        $runtime->combineJS();
        $content = ob_get_contents();
        ob_end_clean();
        $runtime->preloadJS();
        echo $content;
        $runtime->afterLoadJS();
        if($runtime->unfoundApp) {
            echo sprintf("ones.unfoundApp=['%s'];", implode("','", $runtime->unfoundApp));
        }

        break;
}

