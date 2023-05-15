[![Image](../imgs/logo.png)](https://www.philips-hue.com/de-de)

# Hue Grouped Light (Hue Lichtgruppe)

Diese Instanz schaltet eine Gruppe von Philips Hue Lampen.

Zur Verwendung dieses Moduls als Privatperson, Einrichter oder Integrator wenden Sie sich bitte zunächst an den Autor.

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.  
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.  
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.  
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

## Funktionen

---

Die Funktion führt die Aktion der Variable mit der ID `$VariableID` mit dem Wert `$Value` aus.

```text
bool RequestAction(integer $VariableID, mixed $Value);
```

Konnte der Befehl erfolgreich ausgeführt werden, liefert er als Ergebnis `TRUE`, andernfalls `FALSE`.

| Parameter    | Beschreibung    |
|--------------|-----------------|
| `VariableID` | ID der Variable |
| `Value`      | Wert            |

**Beispiel:**

```php
//Gruppe einschalten
RequestAction(12345, true);

//Gruppe ausschalten 
RequestAction(12345, false); 
```

---

Mit dieser Funktion kann eine Gruppe mit weiteren Parametern geschaltet werden.

```text
bool HUE_SetGroup(integer $InstanceID, string $Color, string $OptionalParameters)
```

Konnte der Befehl erfolgreich ausgeführt werden, liefert er als Ergebnis `TRUE`, andernfalls `FALSE`.

| Parameter            | Beschreibung           |
|----------------------|------------------------|
| `InstanceID`         | ID der Gruppen Instanz |
| `Color`              | Farbwert               |
| `OptionalParameters` | Optionale Parameter    |

**Beispiel:**

Die Gruppe soll auf die Farbe F6B859 mit der Helligkeit 100 geschaltet werden und dies mit einem Übergang von 45ms.

```php
$id = 12345;
$color = 'F6B859';
$optionalParameters = json_encode(['on' => ['on' => true], 'dimming' => ['brightness' => 100], 'dynamics' => ['duration' => 45]]);
$result = HUE_SetGroup($id, $color, $optionalParameters);
var_dump($result);
```

---