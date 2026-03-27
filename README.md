🧬 SciSynth v2.0

Le Pont entre l'IA Conversationnelle et la Génomique de Précision

SciSynth est une application "Zero-Config" qui permet aux chercheurs, étudiants et passionnés de génétique de générer des rapports de synthèse scientifique ultra-précis. Contrairement à un ChatGPT standard, SciSynth interroge réellement les bases de données mondiales (NCBI, UniProt, Ensembl) avant de laisser l'IA rédiger la conclusion.

🚀 Exemples d'Applications Pratiques

Cas d'Usage

Question Type

Résultat Attendu

Recherche de Variants

"Quelles sont les implications cliniques du variant p.Val600E sur BRAF ?"

Données ClinVar à jour + bibliographie PubMed associée.

Étude Protéique

"Quelles sont les fonctions de la protéine TP53 et sa localisation ?"

Synthèse UniProt (domaines, fonctions) + coordonnées chromosomiques Ensembl.

Veille Scientifique

"Quelles sont les dernières publications sur CRISPR-Cas9 et le diabète ?"

Extraction des derniers abstracts PubMed et résumé des tendances.

Analyse de Gène

"Donne moi un aperçu du gène BRCA1."

Cartographie complète : du locus chromosomique aux pathologies liées.

🔑 Optimisation Mistral (Free Tier)

SciSynth est conçu pour fonctionner avec le Free Tier de Mistral AI. Comme les comptes gratuits sont limités en requêtes par seconde (Rate Limit), le code implémente une stratégie de rotation :

Multi-Clés : Vous pouvez insérer plusieurs clés API dans le tableau MISTRAL_KEYS.

Failover Automatique : Si une clé atteint sa limite (Erreur 429), le script passe instantanément à la suivante.

Exponential Backoff : Un système de pause intelligente est intégré pour respecter les quotas de l'API sans faire planter la génération du rapport.

🛠️ Installation Rapide

1. Prérequis

Un serveur (XAMPP, WAMP, VPS Linux) avec PHP 8.0+.

Les extensions PHP curl et json (activées par défaut la plupart du temps).

2. Déploiement

Téléchargez le fichier index.php.

Créez un dossier nommé scisynth sur votre serveur et placez-y le fichier.

Configuration des clés : Ouvrez index.php et modifiez la ligne suivante avec vos clés obtenues sur console.mistral.ai :

define('MISTRAL_KEYS', ['votre_cle_1', 'votre_cle_2']);


3. Premier Lancement

Ouvrez votre navigateur sur http://localhost/scisynth/index.php.
L'application va automatiquement créer l'arborescence suivante :

/data/reports/ : Vos synthèses au format JSON.

/data/cache/ : Cache des APIs bio (PubMed/UniProt) pour accélérer les requêtes futures.

/data/chat/ : Votre historique de conversation.

🧠 Architecture & Explication du Code

Le code est structuré de manière procédurale robuste pour garantir la portabilité (un seul fichier suffit) :

A. Le Moteur d'Analyse (NLP Lite)

La fonction analyze_query_with_ai() envoie la question de l'utilisateur à Mistral avec un "System Prompt" strict. L'IA ne répond pas à la question, elle extrait les entités (Gène, Variant, Espèce) et détermine l'intention (Recherche de gène ? Littérature ?).

B. Le Collecteur de Données (Aggregator)

Une fois les entités identifiées :

fetch_pubmed_data() : Interroge l'API eUtils de la NCBI.

fetch_uniprot_data() : Récupère les fiches protéines.

fetch_ensembl_data() : Localise les séquences sur le génome.

C. La Synthèse Finale

Toutes les données brutes (souvent des milliers de lignes de XML/JSON) sont compressées et renvoyées à Mistral. La fonction generate_scientific_report() demande alors à l'IA de rédiger un rapport structuré en utilisant exclusivement les faits trouvés, garantissant ainsi zéro hallucination.

📊 Structure des Données

Les rapports sont sauvegardés en JSON, ce qui permet une réutilisation facile :

{
  "query": "BRCA1",
  "intent": "gene_analysis",
  "data_sources": {
    "pubmed": [...],
    "uniprot": {...}
  },
  "report": {
    "summary": "...",
    "confidence_score": "95%"
  }
}


📜 Licence

Ce projet est sous licence MIT. Vous êtes libre de le modifier et de le distribuer.

Note : Développé par un passionné pour la communauté scientifique. L'utilisation des APIs NCBI est soumise à leurs conditions d'utilisation (limite de 3 requêtes/sec par défaut).
