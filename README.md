🧬 SciSynth v2.0

Intelligence Artificielle & Synthèse Génomique de Précision

SciSynth est une plateforme open-source conçue pour combler le fossé entre la puissance conversationnelle des Large Language Models (LLM) et la rigueur exigée par la recherche scientifique. Ce projet propose une approche "Evidence-Based AI" pour l'analyse de données génétiques et biomédicales.

🔬 Une Solution de Confiance pour la Communauté Scientifique

Dans le paysage actuel, l'utilisation de l'IA en recherche se heurte souvent au problème des hallucinations : les modèles de langage ont tendance à inventer des citations ou des fonctions protéiques inexistantes.

SciSynth inverse ce paradigme. Au lieu de générer du texte à partir de ses propres poids statistiques, l'application utilise l'IA comme un agent d'orchestration.

Étape 1 : L'IA analyse l'intention de la question.

Étape 2 : Le système interroge les bases de données de référence (NCBI PubMed, UniProt, Ensembl, ClinVar).

Étape 3 : L'IA rédige une synthèse en se basant exclusivement sur les données brutes extraites, garantissant une traçabilité totale via des identifiants PMID ou UniProt.

🧠 L'Architecture : Pourquoi ce code est une innovation ?

Le génie de SciSynth réside dans sa structure "Zero-Config & High-Efficiency", optimisée pour la productivité du chercheur :

1. Le Gain de Temps (Workflow unifié)

Une analyse classique de variant demande en moyenne 15 à 20 minutes (navigation entre PubMed, recherche du locus sur Ensembl, lecture des fiches UniProt). SciSynth réduit ce temps à moins de 45 secondes en centralisant l'agrégation des données.

2. Gestion Intelligente des Ressources (Multi-Clés)

Pour contourner les limites des comptes gratuits (Mistral Free Tier), le code intègre un système de rotation de clés API. Si une clé subit un "Rate Limit" (erreur 429), le script bascule sur la suivante sans interruption, garantissant une disponibilité constante.

3. Système de Cache & Persistance

Le code implémente un système de cache local intelligent. Si une donnée a déjà été consultée, elle est servie instantanément, réduisant la charge sur les serveurs de la NCBI et accélérant les consultations répétitives.

4. Portabilité Absolue

Tout le moteur (UI, Logique, API, Gestion de fichiers) tient dans un fichier unique PHP. Aucun besoin de base de données SQL complexe ; SciSynth utilise une structure de stockage JSON à plat pour une installation instantanée.

🚀 Liste des Applications Pratiques

SciSynth s'adapte à de nombreux contextes d'usage :

Domaine

Application

Exemple de requête

Génétique Médicale

Interprétation de variants inconnus (VUS)

"Analyse le variant rs121913527 sur le gène BRCA2"

Recherche Fondamentale

Caractérisation de nouvelles protéines

"Quels sont les domaines fonctionnels de la protéine MSH2 ?"

Pharmacologie

Recherche de cibles thérapeutiques

"Quelles molécules ciblent le récepteur EGFR dans le cancer du poumon ?"

Oncologie

Étude des gènes suppresseurs de tumeurs

"Fais une synthèse des dernières études sur la mutation TP53"

Éducation

Support de cours pour étudiants en Master

"Explique le rôle de la voie de signalisation Wnt"

Bio-informatique

Mapping génomique rapide

"Donne moi les coordonnées chromosomiques exactes de CFTR"

🛠️ Guide d'Installation

1. Prérequis

Un serveur Web (XAMPP, WAMP, ou serveur Linux Apache/Nginx).

PHP 8.0 ou supérieur.

Extension php-curl activée.

2. Mise en place

Déposez le fichier index.php dans votre répertoire de travail.

Créez un dossier /data à la racine (ou laissez le script le créer automatiquement s'il a les droits).

Configuration API : Obtenez vos clés gratuites sur console.mistral.ai et insérez-les en haut du fichier :

// Remplacez par vos propres clés pour la rotation
define('MISTRAL_KEYS', [
    'votre_cle_1',
    'votre_cle_2',
    'votre_cle_3'
]);


3. Arborescence générée

Au premier lancement, le système s'auto-organise :

data/cache/ : Stockage temporaire des réponses API bio.

data/reports/ : Historique de vos rapports de synthèse.

data/chat/ : Sauvegarde des sessions conversationnelles.

📜 Licence & Éthique

Ce projet est distribué sous licence MIT.

Avertissement : Bien que SciSynth soit conçu pour réduire les erreurs de l'IA, les résultats doivent être validés par un expert avant toute décision clinique. L'outil est un assistant de recherche, pas un dispositif de diagnostic.

Développé pour la communauté bio-scientifique — Science Sans Frontières.
