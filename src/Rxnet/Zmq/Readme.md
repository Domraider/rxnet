## Req / Rep
x Req se connectent à 1 Rep. 

Impossible de vérifier que le message a bien été envoyé, mais tant que la socket sera up
le message sera conservé et rien d'autre ne pourra être envoyé dessus

Dès que rep se connectera il recevra le premier message envoyé, les autres ayant généré une erreur devront être essayé manuellement.
 
Scale très bien dans le cas de 1000 req et un rep, par contre lent sur un échange de req / rep sur la même socket.

 * `Acknowledge` côté rep permet d'automatiser la réponse avant tout autre action et de garantir la délégation.
 * `WaitForAnswer` côté req permet d'attendre la réponse avant de faire autre chose (typiquement en délégation)

## Push / Pull
x pull se connectent à 1 push

Le plugin `WaitForMessageToBeSent` garantie que le message a bien été pullé sinon il lance une erreur.

Avec un message brut on monte à 30k msg/s facilement. Si on veut la garantie d'envoit les performances chuttent à 5k/s

Pull ne peut rien communiquer à push, il ne peut que dépiler des messages un par un.

Même si pull bloque sa socket continu de stocker les messages reçu. 
Bien faire attention au Buffer de ZMQ qui si est plein vide.


## Router / Dealer
x dealer se connectent à 1 router

La communication peut aller dans les deux sens sans attente d'un ou de l'autre.

Dans aucun des sens il n'est possible de valider que le message a été envoyé.

Par contre on a la garantie que celui qui envoit stockera tous les messages en attendant que le distant viennent les chercher (dans la limite du buffer)
Il recevra tous les messages d'un coup lorsqu'il se connectera dans l'ordre où ils ont été émis.

## muBroker
Pour la récéption écoute en rep : 
 * delegate : ack dès le message reçu 
 * rpc : attends l'execution complète avant de renvoyer la réponse
 
Chaque message doit avoir un ID unique, un plugin de dédoublonnage peut être mis en entrée
 * deduplicate on count : garde les x derniers messages et rejettent les messages déjà connu
 * deduplicate during : garde pendant x temps les messages rejette les doublons
 
Quand un message est reçu : soit la queue est vide et on a des workers on passe au worker soit on met en queue

Une mailbox se charge de stocker les messages en attendant que des workers soient disponibles
 * memory : stocke en mémoire les messages sous forme d'object queue
 * redis : stock en redis les objets, dans ce cas un poll régulier est fait en plus pour vérifier la base
 * file : stock en fichier, ajoute les nouveaux en fin de fichier et stocke l'état du pointeur lors de la lecture, un démon régulier réécrit le fichier
 * mixed : stocke en mémoire jusqu'à x puis stocke en redis en failover, pareil pour le dépilage
 
Pour le dispatch des jobs on utilise un schéma router / dealer :
 * le worker dit quand il est dispo
 * le dispatcher stock ceux qui sont dispo
 * quand un travail est attribué à un worker il n'est plus dispo
 * quand il est de nouveau dispo il est rajouté dans la liste
 * un keep alive vérifie l'état des workers et les enlévent de la liste si ils sont lents
 * si un worker bloque plus de temps il se fait redémarrer
 
 Chaque worker a un serveur HTTP et a un délai maximum d'execution
 Si le délai d'execution dépasse le maximum, le serveur HTTP répond 500 sinon il répond 200
 Quand il répond 500 il alerte le broker et datadog
 
On peut ajouter un plugin au niveau du router : 
 * garde en mémoire le message envoyé pendant x secondes
 * si le worker n'a pas répondu au cours des x, relance la demande (attention aux doublons)

