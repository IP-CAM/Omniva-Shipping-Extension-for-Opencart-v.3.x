## This module version is deprecated. Since version 2.0.0 it has been moved to https://github.com/mijora/omniva-opencart and is used for both Opencart 2.3 and 3.0



[![Download](https://img.shields.io/badge/dynamic/json.svg?label=download&url=https://api.github.com/repos/mijora/omniva-opencart-3.0-ocmod/releases/latest&query=$.assets[:1].name&style=for-the-badge)](https://github.com/mijora/omniva-opencart-3.0-ocmod/releases/latest)

## Omnivalt Shipping Module

For best usage, make sure Opencart shop is configured.

### Requirements
- Opencart 3.0
- PHP must have permision to write to opencart system folder (module copies omnivalt_base.ocmod.xml file from omnivalt_lib folder to opencart system folder)

### New install
1. Extensions -> Installer Upload omniva-opencart-3.0.ocmod.zip (Recommended)<br/>
  **or**<br/>
  using FTP upload all files from upload folder to root folder of opencart.
2. Edit access permissions:<br/>
  System -> Users -> User Groups edit user group (like Administrator), Select All for both Access and Modify, Save.<br/>
3. Install Omnivalt shipping module<br/>
  Extensions -> Extensions select Shipping from dropdown.<br/>
  Find Omnivalt and press Install (green button).
4. Edit module settings<br/>
  Extensions -> Omnivalt -> Settings<br/>
  Press Save, then 'Update parcel' terminals button to update Omniva terminal list - this might take couple seconds to complete, refresh settings page and check Terminal count at the bottom to see if list was downloaded.
5. Refresh modification cache<br/>
  Extensions -> Modifications.<br/>
  Press Refresh button (top right corner). This will add required changes to template and opencart core files.

### Module update
1. Upload files from new module version using FTP (if currently used version was customized, make sure to have old module backed up and port customizations afterwards into new module version if needed).
2. Edit access permissions (in case there is new module files):<br/>
System -> Users -> User Groups edit user group (like Administrator), Select All for both Access and Modify, Save.
3. Go to module settings:<br/>
this will check for changes and updates needed in database and/or modification XML. Modification file omnivalt_base.ocmod.xml is located in opencart system folder (since module version 1.1.0) for ease of access.
4. Fill/Edit module settings as needed.
