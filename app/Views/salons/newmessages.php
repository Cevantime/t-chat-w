<!-- Exceptionnellement, on n'inscrit pas la vue dans un layout car
elle s'exécute dans un contexte AJAX. Je n'ai donc besoin que de la partie 
qui m'intéresse, à savoir la liste des nouveaux messages
-->
<?php $this->insert('salons/inc.messages',['messages'=>$messages]); ?>
