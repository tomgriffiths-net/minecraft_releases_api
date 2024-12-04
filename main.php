<?php
//Your Settings can be read here: settings::read('myArray/settingName') = $settingValue;
//Your Settings can be saved here: settings::set('myArray/settingName',$settingValue,$overwrite = true/false);
class minecraft_releases_api{
    //public static function command($line):void{}//Run when base command is class name, $line is anything after base command (string). e.g. > [base command] [$line]
    //public static function init():void{}//Run at startup
    public static function getLatest($type = "release"):string|bool{
        $versionsData = json::readFile("https://launchermeta.mojang.com/mc/game/version_manifest.json",false);
        if(isset($versionsData['latest'])){
            if(isset($versionsData['latest'][$type])){
                $latestRelease = $versionsData['latest'][$type];
            }
            else{
                mklog('warning','Attempt made to download from nonexistant minecraft server type: ' . $type,false);
            }
        }
        if(isset($latestRelease)){
            return $latestRelease;
        }
        else{
            return false;
        }
    }
    public static function downloadVersion(string $version):bool{
        $versionsData = json::readFile("https://launchermeta.mojang.com/mc/game/version_manifest.json",false);
        $url = false;
        foreach($versionsData['versions'] as $versionData){
            if($versionData['id'] === $version){
                $url = $versionData['url'];
                break;
            }
        }
        if($url === false){
            return false;
        }
        $versionData = json::readFile($url,false);
        if(isset($versionData['downloads']['server']['url'])){
            downloader::downloadFile($versionData['downloads']['server']['url'],"mcservers/library/vanilla/" . $version . ".jar");
            json::writeFile("mcservers/library/vanilla/" . $version . ".json",$versionData,true);
            return true;
        }
        return false;
    }
    public static function filePath(string $version,$metadata = false,$autoDownload = false):bool|string{
        $dir = "mcservers/library/vanilla/";
        $ext = ".jar";
        if($metadata !== false){
            $ext = ".json";
        }
        $i = 0;
        start:
        if(is_file($dir . $version . $ext)){
            return $dir . $version . $ext;
        }
        else{
            if($autoDownload){
                if($i < 2){
                    self::downloadVersion($version);
                    $i++;
                    goto start;
                }
                else{
                    return false;
                }
            }
            else{
                return false;
            }
        }
    }
    public static function listVersions(string $type):array{
        if($type !== "snapshot"){
            $type = "release";
        }
        $versionsData = json::readFile("https://launchermeta.mojang.com/mc/game/version_manifest.json",false);
        $listedVersions = array();
        foreach($versionsData['versions'] as $versionData){
            if($versionData['type'] === $type){
                $listedVersions[] = $versionData['id'];
            }
        }
        return $listedVersions;
    }
}