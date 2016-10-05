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
