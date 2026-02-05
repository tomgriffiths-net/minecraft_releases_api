<?php
class minecraft_releases_api{
    public static function init():void{
        $defaultSettings = array(
            "libraryDir" => "mcservers\\library\\vanilla"
        );

        foreach($defaultSettings as $settingName => $settingValue){
            settings::set($settingName, $settingValue, false);
        }
    }
    public static function setSetting(string $settingName, mixed $settingValue, bool $overwrite):bool{
        return settings::set($settingName,$settingValue,$overwrite);
    }

    public static function getLatest(string $type="release"):string|false{
        if($type !== "snapshot" && $type !== "release"){
            mklog(2, "Invalid type " . $type);
            return false;
        }

        $versionsData = json::readFile("https://launchermeta.mojang.com/mc/game/version_manifest.json");
        if(!is_array($versionsData)){
            mklog(2, "Failed to read mojang version manifest");
            return false;
        }

        if(!isset($versionsData['latest']) || !is_array($versionsData['latest']) || !isset($versionsData['latest'][$type]) || !is_string($versionsData['latest'][$type])){
            mklog(2, "Failed to find latest info for " . $type);
            return false;
        }

        return $versionsData['latest'][$type];
    }
    public static function downloadVersion(string $version):string|false{
        $versionsData = json::readFile("https://launchermeta.mojang.com/mc/game/version_manifest.json");
        if(!is_array($versionsData)){
            mklog(2, "Failed to read mojang version manifest");
            return false;
        }

        if(!isset($versionsData['versions']) || !is_array($versionsData['versions'])){
            mklog(2, "Failed to read versions list");
            return false;
        }
        
        $url = null;
        foreach($versionsData['versions'] as $versionData){
            if(is_array($versionData) && isset($versionData['id']) && $versionData['id'] === $version){
                $url = $versionData['url'];
                break;
            }
        }
        if(!is_string($url)){
            return false;
        }

        $versionData = json::readFile($url);
        if(!is_array($versionData)){
            mklog(2, "Failed to read mojang version manifest");
            return false;
        }

        if(!isset($versionData['downloads']['server']['url']) || !is_string($versionData['downloads']['server']['url'])){
            mklog(2, "Failed to get download url");
            return false;
        }

        $libraryDir = settings::read("libraryDir");
        if(!is_string($libraryDir)){
            mklog(2, "Failed to read libraryDir setting");
            return false;
        }

        $file = $libraryDir . "\\" . $version . ".jar";
        
        if(!downloader::downloadFile($versionData['downloads']['server']['url'], $file)){
            mklog(2, "Failed to download " . $version . ".jar");
            return false;
        }

        if(isset($versionData['downloads']['server']['sha1']) && is_string($versionData['downloads']['server']['sha1'])){
            if(sha1_file($file) !== $versionData['downloads']['server']['sha1']){
                mklog(2, "The sha1 for " . $version . ".jar did not match the provided sha1");
                unlink($file);
                return false;
            }
        }
        else{
            mklog(2, "Unable to verify sha1 for " . $version . ".jar");
        }

        json::writeFile($libraryDir . "\\" . $version . ".json", $versionData);

        return $file;
    }
    public static function filePath(string $version, bool $metadata=false, bool $autoDownload=true):string|false{
        $libraryDir = settings::read("libraryDir");
        if(!is_string($libraryDir)){
            mklog(2, "Failed to read libraryDir setting");
            return false;
        }

        $ext = $metadata ? ".json" : ".jar";
        $file = $libraryDir . "\\" . $version . $ext;

        if(is_file($file)){
            return $file;
        }

        if(!$autoDownload){
            return false;
        }

        return self::downloadVersion($version);
    }
    public static function listVersions(string $type="release"):array|false{
        if($type !== "snapshot" && $type !== "release"){
            $type = "release";
        }

        $versionsData = json::readFile("https://launchermeta.mojang.com/mc/game/version_manifest.json");
        if(!is_array($versionsData)){
            mklog(2, "Failed to read mojang versions manifest");
            return false;
        }

        if(!isset($versionsData['versions']) || !is_array($versionsData['versions'])){
            mklog(2, "Failed to get versions list");
            return false;
        }

        $listedVersions = [];
        foreach($versionsData['versions'] as $versionData){
            if(is_array($versionData) && isset($versionData['type']) && $versionData['type'] === $type){
                $listedVersions[] = $versionData['id'];
            }
        }

        return $listedVersions;
    }
    public static function minimumJavaVersion(string $version, bool $autoDownload=false):int|false{
        $file = self::filePath($version, true, $autoDownload);
        if(!is_string($file)){
            return false;
        }

        $data = json::readFile($file);
        if(!is_array($data) || !isset($data['javaVersion']['majorVersion']) || !is_int($data['javaVersion']['majorVersion'])){
            return false;
        }

        return $data['javaVersion']['majorVersion'];
    }
    public static function getVersionReleaseTimes(bool $online=true):array|false{
        if($online){
            $versionsData = json::readFile("https://launchermeta.mojang.com/mc/game/version_manifest.json");
            if(!is_array($versionsData)){
                mklog(2, "Failed to read mojang versions manifest");
                return false;
            }

            if(!isset($versionsData['versions']) || !is_array($versionsData['versions'])){
                mklog(2, "Failed to get versions list");
                return false;
            }

            $times = [];
            foreach($versionsData['versions'] as $version){
                if(is_array($version) && isset($version['id']) && is_string($version['id']) && isset($version['releaseTime']) && is_string($version['releaseTime'])){
                    $times[$version['id']] = strtotime($version['releaseTime']);
                }
            }

            $libraryDir = settings::read('libraryDir');
            if(is_string($libraryDir)){
                if(!json::writeFile($libraryDir . "\\versionTimes.json", $times, true)){
                    mklog(2, "Failed to cache minecraft release times");
                }
            }
            else{
                mklog(2, "Failed to read libraryDir setting");
            }

            return $times;
        }
        else{
            $libraryDir = settings::read('libraryDir');
            if(!is_string($libraryDir)){
                mklog(2, "Failed to read libraryDir setting");
                return false;
            }

            $times = json::readFile($libraryDir . "\\versionTimes.json");
            if(!is_array($times)){
                mklog(2, "Failed to read cache for minecraft release times");
                return false;
            }

            return $times;
        }
    }
    public static function compareVersionsUsingTimes(string $v1, string $v2, bool $allowCheckingOnlineForUnknownVersions=true):int{
        $times = self::getVersionReleaseTimes(false);
        if(!is_array($times) || !isset($times[$v1]) || !isset($times[$v2])){
            if($allowCheckingOnlineForUnknownVersions){
                mklog(1, "Checking online for info on " . $v1 . " and " . $v2);
                $times = self::getVersionReleaseTimes(true);
                if(!is_array($times) || !isset($times[$v1]) || !isset($times[$v2])){
                    mklog(2, "Failed to check online for info on " . $v1 . " and " . $v2);
                    return -2;
                }
            }
            else{
                mklog(2, "Failed to get info for " . $v1 . " and " . $v2);
                return -2;
            }
        }

        return $times[$v1] <=> $times[$v2];
    }
}