<?
class HostMonitor extends IPSModule
{

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("HostName", "");
        $this->RegisterPropertyString("HostAdresse", "");
        $this->RegisterPropertyInteger("PingTimeout", "1000");
        $this->RegisterPropertyInteger("Intervall", 60);
        $this->RegisterPropertyInteger("AlarmZeitDiff", 0);
        $this->RegisterPropertyString("BenachrichtigungsTextOffline", "Der Host -§HOST- mit Adresse -§ADRESSE- ist seit §ZEITMIN Minuten nicht mehr erreichbar!");
        $this->RegisterPropertyString("BenachrichtigungsTextOnline", "Der Host -§HOST- mit Adresse -§ADRESSE- war §ZEITMIN Minuten offline und ist jetzt wieder erreichbar!");
        $this->RegisterPropertyInteger("WebFrontInstanceID", 0);
        $this->RegisterPropertyInteger("SmtpInstanceID", 0);
        $this->RegisterPropertyInteger("EigenesSkriptID", 0);
        $this->RegisterPropertyBoolean("LoggingAktiv", false);
        $this->RegisterPropertyBoolean("OfflineBenachrichtigung", false);
        $this->RegisterPropertyBoolean("OnlineBenachrichtigung", false);
        $this->RegisterPropertyBoolean("PushMsgAktiv", false);
        $this->RegisterPropertyBoolean("EMailMsgAktiv", false);
        $this->RegisterPropertyBoolean("EigenesSkriptAktiv", false);
        $this->RegisterTimer("HMON_UpdateTimer", 0, 'HMON_Update($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        $this->UnregisterTimer("HMON_UpdateTimer");
        $this->UnregisterTimer("HMON_BenachrichtigungOfflineTimer");
        
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        
        //Variablenprofil erstellen
        $this->RegisterProfileBooleanEx("HMON.OfflineOnline", "Network", "", "", Array(
                                             Array(false, "Offline",  "", 0xFF0000),
                                             Array(true, "Online",  "", 0x00FF00)
        ));
        
        if (($this->ReadPropertyString("HostName") != "") AND ($this->ReadPropertyString("HostAdresse") != ""))
        {
		        //Status setzen
		        $this->SetStatus(102);
		        //Variablen erstellen
		        $this->RegisterVariableBoolean("HostStatus", "Host - Status", "HMON.OfflineOnline");
		        $this->RegisterVariableBoolean("HostBenachrichtigungsFlag", "Tmp");
		        IPS_SetHidden($this->GetIDForIdent("HostBenachrichtigungsFlag"), true);
		        $this->RegisterVariableInteger("HostLastOnline", "Host - Zuletzt online", "~UnixTimestamp");
		        IPS_SetIcon($this->GetIDForIdent("HostLastOnline"), "Calendar");
		        
		        //Logging aktivieren
		        if ($this->ReadPropertyBoolean("LoggingAktiv") === true)
		        {
		        		$ArchiveHandlerID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
		        		AC_SetLoggingStatus($ArchiveHandlerID, $this->GetIDForIdent("HostStatus"), true);
		        		IPS_ApplyChanges($ArchiveHandlerID);
		        }
		        else
		        {
		        		$ArchiveHandlerID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
		        		AC_SetLoggingStatus($ArchiveHandlerID, $this->GetIDForIdent("HostStatus"), false);
		        		IPS_ApplyChanges($ArchiveHandlerID);
		        }
		        
		        //Timer erstellen
        		$this->SetTimerInterval("HMON_UpdateTimer", $this->ReadPropertyInteger("Intervall"));
        		$this->SetTimerByIdent_InSekunden("HMON_BenachrichtigungOfflineTimer", false);
        		IPS_SetHidden($this->GetIDForIdent("HMON_BenachrichtigungOfflineTimer"), true);
        		
        		//Update
        		$this->Update();
      	}
      	else
      	{
      			$this->SetStatus(206);
      	}
      	
      	//Fehlerhafte Konfiguration melden
      	if (($this->ReadPropertyBoolean("PushMsgAktiv") === true) AND ($this->ReadPropertyInteger("WebFrontInstanceID") == ""))
        {
        		$this->SetStatus(201);
      	}
      	if (($this->ReadPropertyBoolean("EMailMsgAktiv") === true) AND ($this->ReadPropertyInteger("SmtpInstanceID") == ""))
        {
        		$this->SetStatus(202);
      	}
      	if (($this->ReadPropertyBoolean("EigenesSkriptAktiv") === true) AND ($this->ReadPropertyInteger("EigenesSkriptID") == ""))
        {
        		$this->SetStatus(203);
      	}
      	if ((($this->ReadPropertyBoolean("PushMsgAktiv") === false) AND ($this->ReadPropertyBoolean("EMailMsgAktiv") === false) AND ($this->ReadPropertyBoolean("EigenesSkriptAktiv") === false)) AND (($this->ReadPropertyBoolean("OfflineBenachrichtigung") === true)))
      	{
      			$this->SetStatus(204);
      	}
      	if (($this->ReadPropertyBoolean("OfflineBenachrichtigung") === false) AND ($this->ReadPropertyBoolean("OnlineBenachrichtigung") === true))
      	{
      			$this->SetStatus(205);
      	}
    }

    public function Update()
    {
      	$Hostname = $this->ReadPropertyString("HostName");
				$Hostadresse = $this->ReadPropertyString("HostAdresse");
				$PingTimeout = $this->ReadPropertyInteger("PingTimeout");
      	if (($Hostname != "") AND ($Hostadresse != ""))
        {      	
						$result = @Sys_Ping($Hostadresse, $PingTimeout);
						$HostLastOnlineTime = time();
						if ($result === true)
						{
								// OK-Benachrichtung senden, wenn vorher Offline-Benachrichtung gesendet wurde > wenn Einstellung aktiv
								if ((GetValueBoolean($this->GetIDForIdent("HostBenachrichtigungsFlag")) === true) AND ($this->ReadPropertyBoolean("OnlineBenachrichtigung") === true))
								{
										$this->Benachrichtigung(true, true);
								}
								$this->SetValueBoolean("HostBenachrichtigungsFlag", false);
								$this->SetTimerByIdent_InSekunden("HMON_BenachrichtigungOfflineTimer", false);
								$this->SetValueBoolean("HostStatus", $result);
								$this->SetValueInteger("HostLastOnline", $HostLastOnlineTime);
						}
						else
						{
								if ((GetValueBoolean($this->GetIDForIdent("HostBenachrichtigungsFlag")) === false) AND ($this->ReadPropertyBoolean("OfflineBenachrichtigung") === true))
								{
										$BenachrichtigungsTimer = $this->ReadPropertyInteger("AlarmZeitDiff");
										if ($BenachrichtigungsTimer == 0)
										{
												$this->Benachrichtigung(false, true);
										}
										if (GetValueBoolean($this->GetIDForIdent("HostStatus")) === true)
										{
												$this->SetTimerByIdent_InSekunden("HMON_BenachrichtigungOfflineTimer", $BenachrichtigungsTimer);
										}
								}
								$this->SetValueBoolean("HostStatus", $result);
						}
				}
    }

    public function Benachrichtigung($status, $live)
    {
				if ($status == false) 
				{
						$this->SetTimerByIdent_InSekunden("HMON_BenachrichtigungOfflineTimer", false);
						$BenachrichtigungsText = $this->ReadPropertyString("BenachrichtigungsTextOffline");
						$Hoststatus = "offline";
						if ($live == true)
						{
								$this->SetValueBoolean("HostBenachrichtigungsFlag", true);
						}
				}
				elseif ($status == true)
				{
						$BenachrichtigungsText = $this->ReadPropertyString("BenachrichtigungsTextOnline");
						$Hoststatus = "online";
						if ($live == true)
						{
								$this->SetValueBoolean("HostBenachrichtigungsFlag", false);
						}
				}
				$Hostname = $this->ReadPropertyString("HostName");
				$Hostadresse = $this->ReadPropertyString("HostAdresse");
				$LastOnlineTimeDiffSEK = (int)(time() - GetValueInteger($this->GetIDForIdent("HostLastOnline")));
				$LastOnlineTimeDiffMIN = (int)($LastOnlineTimeDiffSEK / 60);
				$LastOnlineTimeDiffSTD = round($LastOnlineTimeDiffMIN / 60, 2);
				$LastOnlineTimeDiffTAGE = round($LastOnlineTimeDiffSTD / 24, 2);
				
				//Code-Wörter austauschen gegen gewünschte Werte
				$search = array("§HOST", "§ADRESSE", "§ZEITSEK", "§ZEITMIN", "§ZEITSTD", "§ZEITTAGE");
				$replace = array($Hostname, $Hostadresse, $LastOnlineTimeDiffSEK, $LastOnlineTimeDiffMIN, $LastOnlineTimeDiffSTD, $LastOnlineTimeDiffTAGE);
				$Text = str_replace($search, $replace, $BenachrichtigungsText);
				$Text = str_replace('Â', '', $Text);
				
				//PUSH-NACHRICHT
				if ($this->ReadPropertyBoolean("PushMsgAktiv") == true)
        {
        		$WFinstanzID = $this->ReadPropertyInteger("WebFrontInstanceID");
        		if (($WFinstanzID != "") AND (@IPS_InstanceExists($WFinstanzID) === true))
        		{
        				WFC_PushNotification($WFinstanzID, "HostMonitor", $Text, "", 0);
        		}
        }
        
        //EMAIL-NACHRICHT
        if ($this->ReadPropertyBoolean("EMailMsgAktiv") == true)
        {
        		$SMTPinstanzID = $this->ReadPropertyInteger("SmtpInstanceID");
        		if (($SMTPinstanzID != "") AND (@IPS_InstanceExists($SMTPinstanzID) === true))
        		{
        				SMTP_SendMail($SMTPinstanzID, "HostMonitor", $Text);
        		}		
        }
        
        //EIGENE-AKTION
        if ($this->ReadPropertyBoolean("EigenesSkriptAktiv") == true)
        {
        		$SkriptID = $this->ReadPropertyInteger("EigenesSkriptID");
        		if (($SkriptID != "") AND (@IPS_ScriptExists($SkriptID) === true))
        		{
        				IPS_RunScriptEx($SkriptID, array("HMON_Name" => $Hostname, "HMON_Adresse" => $Hostadresse, "HMON_Status" => $Hoststatus, "HMON_Text" => $Text, "HMON_Zeit" => $LastOnlineTimeDiffSEK));
        		}		
        }
    }

    private function SetValueBoolean($Ident, $Value)
    {
        $ID = $this->GetIDForIdent($Ident);
        if (GetValueBoolean($ID) <> $Value)
        {
            SetValueBoolean($ID, boolval($Value));
            return true;
        }
        return false;
    }

    private function SetValueInteger($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueInteger($id) <> $value)
        {
            SetValueInteger($id, $value);
            return true;
        }
        return false;
    }
    
    protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize) {
        
        if(!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 0);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if($profile['ProfileType'] != 0)
            throw new Exception("Variable profile type does not match for profile ".$Name);
        }
        
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }
    
    protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations) {
        if ( sizeof($Associations) === 0 ){
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations)-1][0];
        }
        
        $this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        
        foreach($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
        
    }
    
    protected function RegisterTimer($Name, $Interval, $Script)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            $id = 0;


        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception("Ident with name " . $Name . " is used for wrong object type", E_USER_WARNING);

            if (IPS_GetEvent($id)['EventType'] <> 1)
            {
                IPS_DeleteEvent($id);
                $id = 0;
            }
        }

        if ($id == 0)
        {
            $id = IPS_CreateEvent(1);
            IPS_SetParent($id, $this->InstanceID);
            IPS_SetIdent($id, $Name);
        }
        IPS_SetName($id, $Name);
        IPS_SetHidden($id, true);
        IPS_SetEventScript($id, $Script);
        if ($Interval > 0)
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);

            IPS_SetEventActive($id, true);
        } else
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);

            IPS_SetEventActive($id, false);
        }
    }

    protected function UnregisterTimer($Name)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception('Timer not present', E_USER_NOTICE);
            IPS_DeleteEvent($id);
        }
    }
    
    protected function SetTimerInterval($Name, $Interval)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            throw new Exception('Timer not present', E_USER_WARNING);
        if (!IPS_EventExists($id))
            throw new Exception('Timer not present', E_USER_WARNING);

        $Event = IPS_GetEvent($id);

        if ($Interval < 1)
        {
            if ($Event['EventActive'])
                IPS_SetEventActive($id, false);
        }
        else
        {
            if ($Event['CyclicTimeValue'] <> $Interval)
                IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);
            if (!$Event['EventActive'])
                IPS_SetEventActive($id, true);
        }
    }
    
    protected function SetTimerByIdent_InSekunden($ident, $Sekunden)
    {
			   $eid = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
			   if ($eid === false) {
					   	$eid = IPS_CreateEvent(1);
				      IPS_SetParent($eid, $this->InstanceID);
				      IPS_SetName($eid, $ident);
				      IPS_SetIdent($eid, $ident);
				      IPS_SetEventScript($eid, 'HMON_Benachrichtigung($_IPS[\'TARGET\'], false, true);');
				      IPS_SetInfo($eid, "this timer was created by script #".$_IPS['SELF']);
			   }
			   if ($Sekunden === false)
			   {
			      	IPS_SetEventActive($eid, false);
			   			return $eid;
			   }
			   else
			   {
					   	IPS_SetEventCyclicTimeFrom($eid, intval(date("H", time() + $Sekunden)), intval(date("i", time() + $Sekunden)), intval(date("s", time() + $Sekunden)));
					   	IPS_SetEventActive($eid, true);
					   	return $eid;
				}
		}
}
?>