# GLPi Transfer Ticket Entity Plugin (EN)

## Introduction

This plugin enables authorized profiles to transfer tickets from one entity to another to which they do not have access.
It is designed for organizations that have configured their GLPI activity perimeters by entity (HR, Accounting, IS, etc.).

The aim is to be able to transfer a ticket between "GLPI" technicians from different entities, and to continue to ensure the confidentiality of data between entities.

For example: 
An "accounting technician" profile has, by default, visibility only on the tickets of its "Accounting" entity.
If one of the tickets assigned to him concerns the HR department, he will be able to transfer it with follow-up.
Once the transfer has been made, he no longer has any visibility of the ticket.

## Documentation

To configure entity transfer prerequisites :

- In Administration > Profile > YourProfile > Assistance > Ticket : Update must be checked.

- Allow transfer function : defines whether transfer is allowed to the entity and associated groups
- Assigned group required to make a transfer : 
    - Defines whether the transferred ticket must be assigned to a group.
    - If not, the choice "none" will appear in the target group list and must be selected.
    - If the ticket is sent to an entity without a group, it will be considered "new"
- Justification required to make a transfer :
    - If yes, the input field is displayed with a red highlighting.
    - If no, it will appear with a blue highlighting, but the input field will remain active if required.
- Keep category after transfer :
    - If yes, category's ticket will be keep only if it is available in the target entity, otherwise it will be reset to null.
    - If no, category's ticket will be set to null.
    - If no, it's possible to select a default category.
    - :warning: If category is mandatory in the chosen entity (via GLPI's templates) and it will be equal to null after transfer, an error will occur.

## Where set up the plugin

You can configure access rights to the plugin in profile administration.
The transfer prerequisites are managed in the entity administration.

## Compatibility

This plugin has been tested up to GLPI version 11.0.5

## Credits 

Based on https://github.com/Departement-de-Maine-et-Loire/transferticketentity/tree/master
