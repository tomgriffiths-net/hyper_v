<?php
class hyper_v{
    public static function backupVm(string $name, string $destination):bool{
        if(is_admin::check()){
            $info = self::getVmInfo($name);

            $wasRunning = false;
            if($info["state"] === "Running"){
                shell_exec('powershell -command "Save-VM -Name \'' . $info["name"] . '\'"');
                $wasRunning = true;

                if(self::getVmInfo($info["name"])["state"] === "Running"){
                    mklog('warning','Failed to stop VM ' . $info["name"],false);
                    return false;
                }
            }

            $vmConfigLocation = trim(shell_exec('powershell -command "(Get-VM -Name \'' . $info["name"] . '\').ConfigurationLocation"'));
            proc_close(proc_open('robocopy ' . files::validatePath($vmConfigLocation,true) . ' ' . files::validatePath($destination . '\\' . $info["name"],true) . ' /e /v /mir',array(),$pipes));

            if($wasRunning){
                shell_exec('powershell -command "Start-VM -Name \'' . $info["name"] . '\'"');
            }

            return true;
        }
        return false;
    }
    public static function backupAllVms(string $destination){
        if(is_admin::check()){
            $vms = self::listVms();
            foreach($vms as $vm){
                self::backupVm($vm["name"],$destination);
            }
        }
        else{
            mklog('warning','You have to be admin to do that!',false);
        }
    }
    public static function listVms():array{
        $vmInfo = array();
        if(is_admin::check()){
            $vms = shell_exec('powershell -command "Get-VM"');
            if(!empty($vms)){
                $vmInfo = self::parseGetVmsString($vms);
            }
        }
        return $vmInfo;
    }
    public static function getVmInfo(string $name):array{
        $vmInfo = array();
        if(is_admin::check()){
            $vms = shell_exec('powershell -command "Get-VM \'' . $name . '\'"');
            if(!empty($vms)){
                $vmInfo = self::parseGetVmsString($vms)[0];
            }
        }
        return $vmInfo; 
    }
    public static function parseGetVmsString(string $string):array{
        $vmInfo = array();
        if(!empty($string)){

            $vms = explode("\n",$string);
            $starts = array();
            $offset = 0;
            while(true){
                $currentNextDash = strpos($vms[2],"-",$offset);
                if($currentNextDash === false){
                    break;
                }
                $starts[] = $currentNextDash;
                $offset = strpos($vms[2], " ", $currentNextDash);
                if($offset === false){
                    break;
                }
            }

            array_shift($vms);
            array_shift($vms);
            array_shift($vms);
            array_pop($vms);
            array_pop($vms);
            array_pop($vms);

            foreach($vms as $vm){
                $vmInfoPart = array();
                $names = array("name","state","cpu","memory","uptime","status","version");
                $nameIndex = 0;
                foreach($starts as $index => $start){
                    if(isset($starts[$index+1])){
                        $max = $starts[$index+1] - $start;
                    }
                    else{
                        $max = null;
                    }
                    $vmInfoPart[$names[$nameIndex]] = trim(substr($vm,$start,$max));
                    $nameIndex++;
                }
                if(count($vmInfoPart) === 7){
                    $vmInfo[] = $vmInfoPart;
                }
            }
        }
        return $vmInfo;
    }
}