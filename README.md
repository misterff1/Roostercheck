# Roostercheck
Een simpele roosterchecker die gebruik maakt van de Zermelo API.

## Configuratie
Start met het vinden van de "Schoolname" en vul deze in in `config.php`. Je vindt de "Schoolname" in de URL van je Zermelo instantie: https://[Schoolname].zportal.nl

Volg vervolgens deze stappen om een API token te verkrijgen:
1. Ga naar de Zermelo Portal als beheerder en navigeer naar **Beheer > Gebruikers**
2. Selecteer als filter **Geen filter**
3. Klik op de knop **Toevoegen**
4. Maak een gebruiker aan met als gebruikerscode bijv. "roostercheck". Zet ook "mag de gebruiker inloggen?" uit en vink "Toevoegen als werknemer" aan
5. Ga nu naar **Personeel > Overzicht > Contracten**
6. Selecteer het roosterproject van het huidige schooljaar en klik vervolgens op **Toevoegen**
7. Type de naam van je gebruiker (bijv. roostercheck) en klik op **volgende**. Verander vervolgens de functiecategorie naar OOP en klik op **Klaar**
8. Ga nu naar **Beheer > Schoolfuncties > Toekenningen schoolfuncties (details)**
9. Selecteer het roosterproject en de gebruiker en klik op **Toevoegen**
10. Kies als recht **Roosters en afspraken**, als niveau **Project** en bij lezen/bewerken voor **Lezen**
11. Ga nu naar **Beheer > Admin-paneel > API tokens** en klik op **Toevoegen**
12. Selecteer de gebruiker en kies een verloopdatum. Het is aan te raden telkens na een jaar te laten verlopen
13. Vul de API token in bij `config.php`

Nu je een API token hebt rest er nog 1 onderdeel voor de configuratie: de "location". Ga hiervoor naar https://[Schoolname].zportal.nl/static/swagger/ en vul rechtsbovenin de API token in bij **access_token**. Klik vervolgens op **Reload**. Scroll nu naar beneden naar **Branches**, selecteer de GET functie en klik op **Try it out!**. Je krijgt nu in de response een parameter **code** te zien. de letter(s) die daar staan zijn de location code die je in 'config.php' moet invullen. Let op: bij meerdere vestigingen kunnen er meerdere codes zijn. 

## Installatie
Plaats de bestanden, inclusief het ingevulde `config.php` bestand, op een Apache webserver. Je kunt hier nu heen navigeren en d.m.v. een leerlingnummer invullen het rooster zien. Vergeet niet de API token voor de verloopdatum te verversen en om de aangemaakte gebruiker telkens mee te nemen naar het nieuwe roosterproject. 
