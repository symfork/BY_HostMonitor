### IP-Symcon Modul // HostMonitor
---

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Systemanforderungen](#2-systemanforderungen)
3. [Installation](#3-installation)
4. [Befehlsreferenz](#4-befehlsreferenz)
5. [Changelog](#5-changelog) 

## 1. Funktionsumfang
Mit diesem Modul kann man verschiedene Geräte/Webseiten/... mit Ping überwachen/monitoren. Sollte ein Host nicht mehr erreichbar sein,
dann kann man sich entweder sofort oder erst ab einer bestimmten Dauer über verschiedene Wege benachrichtigen lassen. Je Host wird
eine Modul-Instanz erstellt und die gewünschten Einstellungen können vorgenommen werden. Man kann sich beliebig viele dieser Modul-Instanzen
anlegen. Push-Nachrichten und EMail-Benachrichtigung sind eingebaut, weitere/eigene Aktionen sind durch eigene Skripte verwendbar.
  > Eine Online-Benachrichtigung kann nur erfolgen, wenn zuvor eine Offline-Benachrichtigung ausgelöst wurde!

### Einrichtung
Es muss ein Name für den Host eingetragen werden (z.B. "Router"), dann eine IP-Adresse/URL/... über die der Host über PING erreichbar
ist (z.B. "192.168.2.1"). Der Prüf-Intervall legt fest, in welchem Abstand der Host auf Erreichbarkeit geprüft wird.
Je nachdem, ob eine Benachrichtung per EMail/Push/Skript gewünscht ist, muss noch die entsprechende Instanz ausgewählt und auf aktiv
gesetzt werden (Haken setzen). Zusätzlich kann eingetragen werden, wie lange ein Host offline sein darf, bevor eine Benachrichtigung
ausgelöst wird (0 = sofortige Benachrichtigung, wenn Host als Offline erkannt wird).

Ebenfalls kann man ein eigenes Skript festlegen, welches zur Benachrichtigung verwendet wird. Dieses Skript wird bei nicht Erreichbarkeit
des Host, nach eingesteller Zeit oder sofort, ausgeführt. Hier kann man dann Benachrichtungen über Sonos, Enigma2-Nachricht, SMS, ... einrichten.
Für eigene Aktionen stehen einem im ausgewählten Skript die Variablen $_IPS['HMON_Hostname'] (Name des Host), $_IPS['HMON_Adresse'] (Adresse des Host),
$_IPS['HMON_Hoststatus'] (online/offline), $_IPS['HMON_Text'] (Text als String) und $_IPS['HMON_Zeit'] (Sekunden seit letzter Erreichbarkeit)
zur Verfügung (siehe Beispiel-Skript).

#### Beispiel-Skript für eigene Aktion
```php
<?
if ($_IPS["HMON_Hoststatus"] === "offline")
{
	IPS_LogMessage("HostMonitor-OFFLINE", $_IPS['HMON_Text']); // Schreibt den Text ins IPS-Log (zu sehen im Meldungen-Fenster in der IPS-Console)
	Enigma2BY_SendMsg($Enigma2BYinstanzID, $_IPS['HMON_Text'], 3, 10); // Zeigt 10 Sekunden lang eine Alarm-Nachricht über einen Enigma2-Receiver an
}
elseif ($_IPS["HMON_Hoststatus"] === "online")
{
	IPS_LogMessage("HostMonitor-ONLINE", $_IPS['HMON_Text']); // Schreibt den Text ins IPS-Log (zu sehen im Meldungen-Fenster in der IPS-Console)
	Enigma2BY_SendMsg($Enigma2BYinstanzID, $_IPS['HMON_Text'], 1, 10); // Zeigt 10 Sekunden lang eine Info-Nachricht über einen Enigma2-Receiver an
}
?>
```


## 2. Systemanforderungen
- IP-Symcon ab Version 4.x

## 3. Installation
Über die Kern-Instanz "Module Control" folgende URL hinzufügen:

`git://github.com/BayaroX/BY_HostMonitor.git`


## 4. Befehlsreferenz
```php
  HMON_Update($InstanzID);
```
Prüft den in der Instanz eingetragenen Host auf Ereichbarkeit.


## 5. Changelog
Version 1.0:
  - Erster Release
