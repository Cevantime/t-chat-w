<?php

namespace Controller;

use Model\UtilisateursModel;
use W\Security\AuthentificationModel;
use \Respect\Validation\Validator as v;
use \Respect\Validation\Exceptions\ValidationException;

class UserController extends BaseController
{
	/**
	 * Cette fonction sert à afficher la liste des utilisateurs
	 */
	public function listUsers() {
//		$usersList = array(
//			'Googleman', 'Pausewoman', 'Pauseman', 'Roland'
//		);
		
		/*
		 * Ici j'instancie depuis l'action du contrôleur un modèle d'utilisateurs
		 * pour pouvoir accéder à la liste des utilisateurs
		 */
		
		$this->allowTo(['admin', 'superadmin']);
		
		$usersModel = new UtilisateursModel();
		
		$usersList = $usersModel->findAll();
		
		// la ligne suivante affiche la vue présente dans app/Views/users/list.php
		// et y injecte le tableau $usersList sous un nouveau nom $listUsers
		$this->show('users/list', array('listUsers' => $usersList));
	}
	
	public function login() {
//		 on va utiliser le model d'Authentification et plus particulièrement
//		 la méthode isValidLoginInfos à laquelle on passera en param
//		 le pseudo/email et le password envoyés en post par l'utilisateur
//		 une fois cette vérification faite, on récupère l'utilisateur en bdd
//		 on le connecte et on le redirige vers la page d'accueil
		if( !empty($_POST)) {
			// je vérifie la non-vacuité du pseudo en POST
			if( empty($_POST['pseudo'])) {
				// si le pseudo est vide on ajoute un message d'erreur
				$this->getFlashMessenger()->error('Veuillez entrer un pseudo');
			}
			// je vérifie la non-vacuité du mot de passe en POST
			if( empty($_POST['mot_de_passe'])) {
				// si le mot de passe est vide on ajoute un message d'erreur
				$this->getFlashMessenger()->error('Veuillez entrer un mot de passe');
			}
			
			$auth = new AuthentificationModel();
			
			if( ! $this->getFlashMessenger()->hasErrors()) {
				// vérification de l'existence de l'utilisateur
				$idUser = $auth->isValidLoginInfo($_POST['pseudo'], $_POST['mot_de_passe']);
				
				// si l'utilisateur existe bel et bien...
				if($idUser !== 0){
					$utilisateurModel = new UtilisateursModel();
					
					// ... je récupère les infos de l'utilisateur et je m'en
					// sers pour le connecter au site à l'aide de $auth->logUserIn
					$userInfos = $utilisateurModel->find($idUser);
					$auth->logUserIn($userInfos);
					
					// une fois l'utilisateur connecté, je le redirige vers l'accueil
					$this->getFlashMessenger()->success('Vous vous êtes connecté avec succès !');
					$this->redirectToRoute('default_home');
				} else {
					// les infos de connexion sont incorrectes, on avertit 
					// l'utilisateur
					$this->getFlashMessenger()->error('Vos informations de connexion sont incorrectes');
				}
				
			}
			
		}
		
		$this->show('users/login', array('datas' => isset($_POST) ? $_POST : array()));
	}
	
	public function logout() {
		$auth = new AuthentificationModel();
		$auth->logUserOut();
		$this->redirectToRoute('login');
	}
	
	public function register() {
		
		$user = $this->getUser();
		
		$datas = $user ? $user : array();
				
		if( ! empty($_POST)) {
			
			// on indique à respect validation que nos règles de validation
			// seront accessibles depuis le namespace Validation\Rules
			v::with("Validation\Rules");
			
			// je déclare mes validateurs pseudo et email à part
			// car je souhaite effectuer des vérifications supplémentaires
			// uniquement si l'utilisateur est en mode insertion (pas connecté)
			$pseudoValidator = v::length(3,50)
					->alnum()
					->noWhitespace()
					->setName('Nom d\'utilisateur');
			
			$emailValidator = v::email()
					->setName('Email');
			
			// si l'utilisateur n'est pas connecté (mode insertion) 
			// on ajoute les conditions "l'email ne doit pas déjà exister" et 
			// "le nom d'utilisateur ne doit pas exister"
			if( ! $user) {
				$pseudoValidator->usernameNotExists();
				$emailValidator->emailNotExists();
			}
			
			$validators = array(
				'pseudo' => $pseudoValidator,
				
				'email' => $emailValidator,
				
				'mot_de_passe' => v::length(3,50)
					->alnum()
					->noWhiteSpace()
					->setName('Mot de passe'),
				
				'sexe' => v::in(array('femme', 'homme', 'non-défini'))
					->setName('Sexe'),
				
				'avatar' => v::optional(
						v::image()
						->size('0', '1MB')
						->uploaded()
					)
				
			);
			
			$datas = $_POST;
			
			// on ajouter le chemin vers le fichier d'avatar qui a été uploadé
			// (s'il y en a un)
			
			if( !empty($_FILES['avatar']['tmp_name'])) {
				// je stocke en données à valider le chemin vers la localisation
				// temporaire de l'avatar
				$datas['avatar'] = $_FILES['avatar']['tmp_name'];
			} else {
				// sinon je laisse ce champ vide
				$datas['avatar'] = '';
			}
			
			// localisation rapide de mes messages d'erreur (devrait être déclaré 
			// dans un fichier à part)
			$trads = array(
				'alnum' => '{{name}} ne doit contenir que des caractères alphanumériques',
				'size' => '{{name}} doit avoir une taille comprise entre {{minSize}} et {{maxSize}}',
				'upload' => '{{name}} n\'a pas été uploadé correctement',
				'length' => '{{name}} doit avoir une longueur comprise entre {{minValue}} et {{maxValue}} caractères',
				'noWhitespace' => '{{name}} ne doit pas contenir d\'espace vide',
				'in' => '{{name}} doit être compris dans {{haystack}}',
				'image' => '{{name}} doit être une image',
				'usernameNotExists' => 'Votre {{name}} existe déjà',
				'emailNotExists' => 'Votre {{name}} existe déjà',
				'email' => "L'email entré n'est pas valide"
			);
			// je parcours la liste de mes validateurs en récupérant aussi le
			// nom du champ en clé
			foreach ($validators as $field => $validator) {
				// la méthode assert renvoie une exception de type NestedValidationException
				// qui nous permet de récupérer le ou les messages d'erreur
				// en cas d'erreur.
				try{
					// on essaye de valider la donnée
					// si une exception se produit, c'est le bloc catch qui sera
					// exécuté
					$validator->check(isset($datas[$field]) ? $datas[$field] : '');
				} catch (ValidationException $ex) {
					$ex->setTemplate($trads[$ex->getId()]);
					$mainMessage = $ex->getMainMessage();
					$this->getFlashMessenger()->error($mainMessage);
				}
			}
			
			if( ! $this->getFlashMessenger()->hasErrors()) {
				// si on n'a pas rencontré d'erreur, on procède à l'insertion
				// du nouvel utilisateur
				
				// avant l'insertion, on doit faire deux choses :
				// - déplacer l'avatar du fichier temporaire vers le dossiers avatars/
				// - hâcher le mot de passe
				
				// on hâche d'abord le mot de passe. On utilise pour cela le modèle
				// AuthentificationModel pour rester cohérent avec le framework
				
				$auth = new AuthentificationModel(); 
				
				$datas['mot_de_passe'] = $auth->hashPassword($datas['mot_de_passe']);
				
				// on déplace l'avatar vers le dossier avatars
				
				if( ! empty($_FILES['avatar']['tmp_name'])) {
					$initialAvatarPath = $_FILES['avatar']['tmp_name'];
					$avatarNewName = md5(time() . uniqid());

					$targetPath = realpath('assets/uploads');
					
					move_uploaded_file($initialAvatarPath, $targetPath.'/'.$avatarNewName);

					// on met à jour le nouveau nom de l'avatar dans $datas
					$datas['avatar'] = $avatarNewName;
				} else {
					$datas['avatar'] = 'default.png';
				}
				
				
				$utilisateursModel = new UtilisateursModel();
				
				unset($datas['send']);
				
				if($user) {
					// UPDATE utilisateurs SET ... WHERE id = ...
					$utilisateursModel->update($datas, $user['id']);
					$auth = new AuthentificationModel();
					$this->getFlashMessenger()->success('Vous avez bien mis à jour votre profil');
					if( ! $auth->refreshUser()) {
						$this->getFlashMessenger()->warning('Nous n\'avons pas été en mesure de vous reconnecter');
					}
				} else {
					$this->getFlashMessenger()->success('Vous vous êtes bien inscrit à T\'Chat !');
					$userInfos = $utilisateursModel->insert($datas);
					$auth->logUserIn($userInfos);
				}
				
				$this->redirectToRoute('default_home');
			}
		}
		
		$this->show('users/register',  array('datas' => $datas));
	}

}