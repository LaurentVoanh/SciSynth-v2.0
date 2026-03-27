<?php
/**
 * SciSynth v2.0 — Moteur de Synthèse Scientifique Conversationnel
 * Interface humaine, analyse intelligente de questions naturelles
 * 
 * License: MIT | Auteur: Expert ADN & IA
 */

// ============================================================================
// 🔑 CONFIGURATION — Clés Mistral (Free Tier Dev)
// ============================================================================
define('MISTRAL_KEYS', [
    'api key mistral 1',
    'api key mistral 2',
    'api key mistral 3'
]);
define('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('DEFAULT_MODEL', 'open-mistral-7b');

// ============================================================================
// ⚙️ INITIALISATION
// ============================================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(180);
date_default_timezone_set('UTC');
session_start();

// ============================================================================
// 📁 GESTION DES FICHIERS & DOSSIERS
// ============================================================================
function ensure_dir($path) {
    if (!file_exists($path)) {
        @mkdir($path, 0755, true);
        @chmod($path, 0755);
    }
    return is_writable($path);
}

function init_structure() {
    $base = __DIR__ . '/data';
    $dirs = ['reports', 'cache', 'logs', 'config', 'self_improve', 'knowledge', 'chat'];
    foreach ($dirs as $dir) {
        ensure_dir("$base/$dir");
    }
    
    $configPath = "$base/config/config.json";
    if (!file_exists($configPath)) {
        $defaultConfig = [
            'version' => '2.0.0',
            'installed_at' => date('c'),
            'active_model' => DEFAULT_MODEL,
            'auto_improve' => true,
            'cache_ttl' => 3600,
            'language' => 'fr'
        ];
        @file_put_contents($configPath, json_encode($defaultConfig, JSON_PRETTY_PRINT));
    }
}

// ============================================================================
// 📄 UTILITAIRES JSON
// ============================================================================
function save_json($path, $data) {
    $dir = dirname($path);
    ensure_dir($dir);
    return @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function load_json($path, $default = null) {
    if (!file_exists($path)) return $default;
    $content = @file_get_contents($path);
    $data = json_decode($content, true);
    return $data ?? $default;
}

// ============================================================================
// 📝 LOGGING
// ============================================================================
function log_activity($message, $level = 'info', $context = []) {
    $logPath = __DIR__ . '/data/logs/activity.log';
    $entry = [
        'timestamp' => date('c'),
        'level' => $level,
        'message' => $message,
        'context' => $context
    ];
    @file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

// ============================================================================
// 🤖 MISTRAL API — Rotation des Clés
// ============================================================================
function mistral_request($prompt, $model = null, $systemPrompt = '', $maxRetries = 3) {
    $model = $model ?? DEFAULT_MODEL;
    $keys = array_values(MISTRAL_KEYS);
    
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $apiKey = $keys[$attempt % count($keys)];
        
        $payload = [
            'model' => $model,
            'messages' => [],
            'max_tokens' => 2500,
            'temperature' => 0.2,
            'top_p' => 0.9
        ];
        
        if ($systemPrompt) {
            $payload['messages'][] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $payload['messages'][] = ['role' => 'user', 'content' => $prompt];
        
        $ch = curl_init(MISTRAL_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            return ['success' => true, 'content' => $content, 'usage' => $data['usage'] ?? []];
        }
        
        if ($httpCode === 429) { usleep(500000); continue; }
        if ($httpCode >= 500) { sleep(1); continue; }
        
        return ['success' => false, 'error' => "HTTP $httpCode", 'http_code' => $httpCode];
    }
    
    return ['success' => false, 'error' => 'Max retries exceeded'];
}

// ============================================================================
// 🔬 APIs SCIENTIFIQUES
// ============================================================================
function fetch_pubmed($query, $retmax = 5) {
    $cacheKey = 'pubmed_' . md5($query);
    $cachePath = __DIR__ . "/data/cache/$cacheKey.json";
    
    if (file_exists($cachePath) && (time() - filemtime($cachePath) < 3600)) {
        return load_json($cachePath);
    }
    
    $url = sprintf('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=%s&retmax=%d&retmode=json', 
                  urlencode($query), $retmax);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $ids = $data['esearchresult']['idlist'] ?? [];
    
    $articles = [];
    if (!empty($ids)) {
        $fetchUrl = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id=' . implode(',', $ids) . '&retmode=json';
        $ch = curl_init($fetchUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $summary = curl_exec($ch);
        curl_close($ch);
        $summaryData = json_decode($summary, true);
        
        foreach ($ids as $id) {
            $item = $summaryData['result'][$id] ?? null;
            if ($item) {
                $articles[] = [
                    'pmid' => $id,
                    'title' => $item['title'] ?? '',
                    'source' => $item['source'] ?? '',
                    'pubdate' => $item['pubdate'] ?? ''
                ];
            }
        }
    }
    
    $result = ['query' => $query, 'count' => count($articles), 'articles' => $articles];
    save_json($cachePath, $result);
    return $result;
}

function fetch_uniprot($geneSymbol) {
    $cacheKey = 'uniprot_' . md5($geneSymbol);
    $cachePath = __DIR__ . "/data/cache/$cacheKey.json";
    
    if (file_exists($cachePath) && (time() - filemtime($cachePath) < 7200)) {
        return load_json($cachePath);
    }
    
    $url = sprintf('https://rest.uniprot.org/uniprotkb/search?query=gene:%s&format=json&size=3', urlencode($geneSymbol));
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $results = $data['results'] ?? [];
    
    $simplified = [];
    foreach ($results as $item) {
        $simplified[] = [
            'accession' => $item['primaryAccession'] ?? '',
            'name' => $item['uniProtkbId'] ?? '',
            'description' => $item['proteinDescription']['recommendedName']['fullName']['value'] ?? '',
            'organism' => $item['organism']['scientificName'] ?? ''
        ];
    }
    
    $result = ['gene' => $geneSymbol, 'count' => count($simplified), 'entries' => $simplified];
    save_json($cachePath, $result);
    return $result;
}

function fetch_ensembl($geneSymbol) {
    $cacheKey = 'ensembl_' . md5($geneSymbol);
    $cachePath = __DIR__ . "/data/cache/$cacheKey.json";
    
    if (file_exists($cachePath) && (time() - filemtime($cachePath) < 7200)) {
        return load_json($cachePath);
    }
    
    $url = sprintf('https://rest.ensembl.org/lookup/symbol/homo_sapiens/%s?content-type=application/json', urlencode($geneSymbol));
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) return ['error' => "HTTP $httpCode"];
    
    $data = json_decode($response, true);
    $result = [
        'gene' => $geneSymbol,
        'ensembl_id' => $data['id'] ?? '',
        'chromosome' => $data['seq_region_name'] ?? '',
        'description' => $data['description'] ?? ''
    ];
    
    save_json($cachePath, $result);
    return $result;
}

function fetch_clinvar($variant) {
    $cacheKey = 'clinvar_' . md5($variant);
    $cachePath = __DIR__ . "/data/cache/$cacheKey.json";
    
    if (file_exists($cachePath) && (time() - filemtime($cachePath) < 3600)) {
        return load_json($cachePath);
    }
    
    $url = sprintf('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=clinvar&term=%s&retmax=5&retmode=json', urlencode($variant));
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $ids = $data['esearchresult']['idlist'] ?? [];
    
    $result = ['query' => $variant, 'count' => count($ids), 'ids' => $ids];
    save_json($cachePath, $result);
    return $result;
}

// ============================================================================
// 🧠 ANALYSEUR DE QUESTION NATURELLE
// ============================================================================
function analyze_question($question) {
    $systemPrompt = "Tu es un assistant qui analyse les questions scientifiques en génétique/bio-informatique.
Extrait les informations suivantes au format JSON STRICT :
{
  \"intent\": \"gene|variant|literature|general\",
  \"gene\": \"nom du gène ou null\",
  \"variant\": \"notation du variant ou null\",
  \"topic\": \"sujet principal reformulé\",
  \"confidence\": 0.0-1.0
}

Règles :
- intent='gene' si on parle d'un gène (BRCA1, TP53, etc.)
- intent='variant' si on parle d'une mutation (c.68_69del, p.Cys61Gly, etc.)
- intent='literature' si on cherche des articles sur un sujet
- intent='general' pour les questions générales

Réponds UNIQUEMENT le JSON, rien d'autre.";

    $response = mistral_request($question, DEFAULT_MODEL, $systemPrompt);
    
    if (!$response['success']) {
        return [
            'intent' => 'general',
            'gene' => null,
            'variant' => null,
            'topic' => $question,
            'confidence' => 0.5,
            'error' => $response['error']
        ];
    }
    
    $parsed = json_decode($response['content'], true);
    if (!$parsed) {
        return [
            'intent' => 'general',
            'gene' => null,
            'variant' => null,
            'topic' => $question,
            'confidence' => 0.5
        ];
    }
    
    return $parsed;
}

// ============================================================================
// 🧠 GÉNÉRATION DE RAPPORT
// ============================================================================
function generate_report($topic, $gene = null, $variant = null) {
    log_activity('Génération rapport', 'info', ['topic' => $topic, 'gene' => $gene]);
    
    $data = ['topic' => $topic, 'collected_at' => date('c'), 'sources' => []];
    
    if ($gene) {
        $data['sources']['pubmed'] = fetch_pubmed("$gene function");
        $data['sources']['uniprot'] = fetch_uniprot($gene);
        $data['sources']['ensembl'] = fetch_ensembl($gene);
    }
    if ($variant) {
        $data['sources']['clinvar'] = fetch_clinvar($variant);
    }
    
    $systemPrompt = "Tu es un assistant de recherche scientifique expert. Tes réponses :
- Strictement basées sur les données fournies
- Citées avec PMID, UniProt ID quand disponible
- Nuancées : limites, contradictions
- Structurées : résumé → preuves → confiance → gaps → pistes
- En français scientifique clair";

    $userPrompt = "Sujet : $topic\n";
    if ($gene) $userPrompt .= "Gène : $gene\n";
    if ($variant) $userPrompt .= "Variant : $variant\n";
    
    $userPrompt .= "\nDonnées :\n";
    $userPrompt .= "PubMed: " . json_encode($data['sources']['pubmed'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    $userPrompt .= "UniProt: " . json_encode($data['sources']['uniprot'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    $userPrompt .= "Ensembl: " . json_encode($data['sources']['ensembl'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    if (isset($data['sources']['clinvar'])) {
        $userPrompt .= "ClinVar: " . json_encode($data['sources']['clinvar'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    $userPrompt .= "\nProduis un rapport avec : 1) Résumé 2) Preuves (citations) 3) Confiance 4) Gaps 5) Pistes 6) BibTeX";

    $aiResponse = mistral_request($userPrompt, null, $systemPrompt);
    
    if (!$aiResponse['success']) {
        return ['success' => false, 'error' => $aiResponse['error']];
    }
    
    $reportId = 'rpt_' . date('Ymd_His') . '_' . substr(md5($topic . time()), 0, 8);
    $report = [
        'id' => $reportId,
        'topic' => $topic,
        'gene' => $gene,
        'variant' => $variant,
        'generated_at' => date('c'),
        'model_used' => DEFAULT_MODEL,
        'tokens_used' => $aiResponse['usage']['total_tokens'] ?? null,
        'content' => $aiResponse['content'],
        'raw_data' => $data,
        'citations' => extract_citations($aiResponse['content'])
    ];
    
    save_json(__DIR__ . "/data/reports/$reportId.json", $report);
    log_activity('Rapport généré', 'info', ['report_id' => $reportId]);
    
    return ['success' => true, 'report_id' => $reportId, 'content' => $aiResponse['content']];
}

function extract_citations($text) {
    $citations = [];
    if (preg_match_all('/PMID[:\s]*(\d+)/i', $text, $matches)) {
        $citations['pmid'] = array_unique($matches[1]);
    }
    if (preg_match_all('/10\.\d+\/[\w\.-]+/i', $text, $matches)) {
        $citations['doi'] = array_unique($matches[0]);
    }
    return $citations;
}

// ============================================================================
// 💬 GESTION DU CHAT
// ============================================================================
function save_chat_message($sessionId, $role, $content, $reportId = null) {
    $chatPath = __DIR__ . "/data/chat/{$sessionId}.json";
    $chat = load_json($chatPath, ['messages' => [], 'started_at' => date('c')]);
    
    $chat['messages'][] = [
        'role' => $role,
        'content' => $content,
        'timestamp' => date('c'),
        'report_id' => $reportId
    ];
    $chat['updated_at'] = date('c');
    
    save_json($chatPath, $chat);
    return $chat;
}

function get_chat_history($sessionId) {
    return load_json(__DIR__ . "/data/chat/{$sessionId}.json", ['messages' => []]);
}

// ============================================================================
// 🎨 INTERFACE — HEADER
// ============================================================================
function render_header($title = 'SciSynth') {
    $config = load_json(__DIR__ . '/data/config/config.json', []);
    $version = $config['version'] ?? '2.0.0';
    
    echo '<!DOCTYPE html>';
    echo '<html lang="fr">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<style>';
    echo ':root { --bg: #0a0a0f; --card: #1a1a2e; --accent: #00d4ff; --success: #00ff88; --warn: #ffaa00; --error: #ff4444; --text: #e0e0e0; --muted: #888; }';
    echo '* { box-sizing: border-box; margin: 0; padding: 0; }';
    echo 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }';
    echo '.container { max-width: 1200px; margin: 0 auto; padding: 20px; }';
    echo 'header { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #333; margin-bottom: 20px; }';
    echo '.logo { font-size: 1.5em; font-weight: bold; color: var(--accent); }';
    echo '.logo span { color: var(--success); }';
    echo 'nav a { color: var(--text); text-decoration: none; margin-left: 20px; }';
    echo 'nav a:hover { color: var(--accent); }';
    echo '.chat-container { display: grid; grid-template-columns: 1fr 400px; gap: 20px; height: calc(100vh - 180px); }';
    echo '.chat-main { background: var(--card); border-radius: 12px; padding: 20px; overflow-y: auto; }';
    echo '.chat-sidebar { background: var(--card); border-radius: 12px; padding: 20px; overflow-y: auto; }';
    echo '.message { margin: 15px 0; padding: 15px; border-radius: 10px; }';
    echo '.message.user { background: #2a2a4e; border-left: 3px solid var(--accent); }';
    echo '.message.assistant { background: #1a2a2e; border-left: 3px solid var(--success); }';
    echo '.message.system { background: #2a1a1e; border-left: 3px solid var(--warn); }';
    echo '.input-area { display: flex; gap: 10px; margin-top: 20px; }';
    echo '.input-area textarea { flex: 1; padding: 12px; background: #0f0f1a; border: 1px solid #333; border-radius: 8px; color: var(--text); resize: none; min-height: 60px; }';
    echo '.input-area textarea:focus { outline: none; border-color: var(--accent); }';
    echo '.btn { padding: 12px 24px; background: var(--accent); color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }';
    echo '.btn:hover { background: var(--success); }';
    echo '.btn:disabled { background: #555; cursor: not-allowed; }';
    echo '.report-card { background: #0f0f1a; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #333; }';
    echo '.report-card h4 { color: var(--accent); margin-bottom: 8px; }';
    echo '.report-card pre { background: #0a0a0f; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 0.85em; white-space: pre-wrap; }';
    echo '.status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75em; }';
    echo '.status-badge.success { background: #00ff8822; color: var(--success); }';
    echo '.status-badge.warn { background: #ffaa0022; color: var(--warn); }';
    echo '.progress-steps { margin: 15px 0; }';
    echo '.progress-step { display: flex; align-items: center; gap: 10px; margin: 8px 0; color: var(--muted); }';
    echo '.progress-step.active { color: var(--accent); }';
    echo '.progress-step.done { color: var(--success); }';
    echo '.step-icon { width: 20px; height: 20px; border-radius: 50%; background: #333; display: flex; align-items: center; justify-content: center; font-size: 0.7em; }';
    echo '.progress-step.done .step-icon { background: var(--success); color: #000; }';
    echo '.progress-step.active .step-icon { background: var(--accent); color: #000; animation: pulse 1.5s infinite; }';
    echo '@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }';
    echo '.history-item { padding: 10px; border-bottom: 1px solid #333; cursor: pointer; }';
    echo '.history-item:hover { background: #2a2a3e; }';
    echo '.disclaimer { background: #ff444411; border-left: 3px solid var(--error); padding: 12px; margin: 20px 0; border-radius: 0 5px 5px 0; font-size: 0.85em; }';
    echo 'footer { text-align: center; padding: 20px; color: var(--muted); font-size: 0.9em; border-top: 1px solid #333; margin-top: 20px; }';
    echo '@media (max-width: 900px) { .chat-container { grid-template-columns: 1fr; } .chat-sidebar { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="container">';
    echo '<header>';
    echo '<div class="logo">Sci<span>Synth</span> v' . htmlspecialchars($version) . '</div>';
    echo '<nav>';
    echo '<a href="?page=chat">💬 Chat</a>';
    echo '<a href="?page=reports">📄 Rapports</a>';
    echo '<a href="?page=settings">⚙️ Réglages</a>';
    echo '</nav>';
    echo '</header>';
}

function render_footer() {
    echo '<footer>';
    echo '<p>⚠️ Outil de recherche uniquement. Ne remplace pas l\'expertise humaine.</p>';
    echo '<p style="margin-top:8px;">Données : PubMed, UniProt, Ensembl, ClinVar | IA : Mistral AI</p>';
    echo '</footer>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
}

// ============================================================================
// 💬 PAGE CHAT (Interface Principale)
// ============================================================================
function render_chat_page() {
    $sessionId = session_id();
    $chat = get_chat_history($sessionId);
    
    echo '<div class="chat-container">';
    echo '<div class="chat-main">';
    echo '<h2 style="margin-bottom:15px;">💬 Posez votre question scientifique</h2>';
    echo '<p style="color:var(--muted);margin-bottom:20px;">Exemples : "Quel est le rôle de BRCA1 dans le cancer du sein ?", "Le variant c.68_69del est-il pathogène ?", "Dernières avancées sur l\'immunothérapie du mélanome"</p>';
    
    // Messages existants
    foreach ($chat['messages'] as $msg) {
        $role = $msg['role'];
        $content = htmlspecialchars($msg['content']);
        $time = date('H:i', strtotime($msg['timestamp']));
        
        echo '<div class="message ' . $role . '">';
        echo '<div style="font-size:0.85em;color:var(--muted);margin-bottom:5px;">' . ($role === 'user' ? '👤 Vous' : '🤖 SciSynth') . ' • ' . $time . '</div>';
        echo '<div>' . nl2br($content) . '</div>';
        
        if (isset($msg['report_id']) && $msg['report_id']) {
            echo '<div style="margin-top:10px;"><a href="?page=report&id=' . htmlspecialchars($msg['report_id']) . '" class="btn" style="padding:6px 12px;font-size:0.85em;">📄 Voir le rapport complet</a></div>';
        }
        echo '</div>';
    }
    
    // Zone de saisie
    echo '<div class="input-area">';
    echo '<form id="questionForm" method="post" action="?action=submit_question">';
    echo '<textarea name="question" id="questionInput" placeholder="Posez votre question sur un gène, variant, ou sujet de recherche..." required></textarea>';
    echo '<button type="submit" class="btn" id="submitBtn">Envoyer</button>';
    echo '</form>';
    echo '</div>';
    
    // Progress (caché par défaut)
    echo '<div id="progressArea" style="display:none;margin-top:20px;">';
    echo '<div class="progress-steps">';
    echo '<div class="progress-step" id="step1"><div class="step-icon">1</div><span>Analyse de la question...</span></div>';
    echo '<div class="progress-step" id="step2"><div class="step-icon">2</div><span>Collecte des données scientifiques...</span></div>';
    echo '<div class="progress-step" id="step3"><div class="step-icon">3</div><span>Synthèse par l\'IA...</span></div>';
    echo '<div class="progress-step" id="step4"><div class="step-icon">4</div><span>Rapport généré !</span></div>';
    echo '</div>';
    echo '<p style="color:var(--muted);font-size:0.9em;">⏱️ Temps estimé : 30-60 secondes</p>';
    echo '</div>';
    
    echo '</div>';
    
    // Sidebar — Historique
    echo '<div class="chat-sidebar">';
    echo '<h3 style="margin-bottom:15px;">📜 Historique</h3>';
    
    $reportsDir = __DIR__ . '/data/reports';
    $reports = [];
    if (is_dir($reportsDir)) {
        $files = array_filter(scandir($reportsDir), function($f) { return str_ends_with($f, '.json'); });
        rsort($files);
        foreach (array_slice($files, 0, 10) as $file) {
            $rpt = load_json("$reportsDir/$file");
            if ($rpt) {
                $date = date('d/m H:i', strtotime($rpt['generated_at']));
                echo '<div class="history-item" onclick="location.href=\'?page=report&id=' . htmlspecialchars($rpt['id']) . '\'">';
                echo '<div style="font-weight:500;color:var(--accent);">' . htmlspecialchars(mb_strimwidth($rpt['topic'], 0, 40, '…')) . '</div>';
                echo '<div style="font-size:0.8em;color:var(--muted);">' . $date . '</div>';
                echo '</div>';
            }
        }
    }
    
    if (empty($reports)) {
        echo '<p style="color:var(--muted);font-size:0.9em;">Aucun rapport encore.</p>';
    }
    
    echo '</div>';
    echo '</div>';
    
    // Script pour animation
    echo '<script>';
    echo 'const form = document.getElementById("questionForm");';
    echo 'const progressArea = document.getElementById("progressArea");';
    echo 'const submitBtn = document.getElementById("submitBtn");';
    echo 'const questionInput = document.getElementById("questionInput");';
    echo '';
    echo 'form.addEventListener("submit", function(e) {';
    echo '  e.preventDefault();';
    echo '  const question = questionInput.value.trim();';
    echo '  if (!question) return;';
    echo '  ';
    echo '  submitBtn.disabled = true;';
    echo '  submitBtn.textContent = "En cours...";';
    echo '  progressArea.style.display = "block";';
    echo '  ';
    echo '  // Animation des étapes';
    echo '  setTimeout(() => { document.getElementById("step1").classList.add("active"); }, 100);';
    echo '  setTimeout(() => { document.getElementById("step1").classList.remove("active"); document.getElementById("step1").classList.add("done"); document.getElementById("step2").classList.add("active"); }, 5000);';
    echo '  setTimeout(() => { document.getElementById("step2").classList.remove("active"); document.getElementById("step2").classList.add("done"); document.getElementById("step3").classList.add("active"); }, 15000);';
    echo '  setTimeout(() => { document.getElementById("step3").classList.remove("active"); document.getElementById("step3").classList.add("done"); document.getElementById("step4").classList.add("active"); }, 35000);';
    echo '  ';
    echo '  // Soumission réelle';
    echo '  setTimeout(() => { form.submit(); }, 500);';
    echo '});';
    echo '</script>';
}

// ============================================================================
// 📄 PAGE RAPPORT
// ============================================================================
function render_report_page($reportId) {
    $report = load_json(__DIR__ . "/data/reports/$reportId.json");
    
    if (!$report) {
        echo '<div class="disclaimer">❌ Rapport non trouvé</div>';
        echo '<a href="?page=chat" class="btn">← Retour au chat</a>';
        return;
    }
    
    $topic = htmlspecialchars($report['topic']);
    $content = htmlspecialchars($report['content']);
    $generatedAt = date('d/m/Y H:i', strtotime($report['generated_at']));
    $modelUsed = htmlspecialchars($report['model_used']);
    
    echo '<h2 style="margin-bottom:15px;">📄 Rapport : ' . $topic . '</h2>';
    echo '<div style="display:flex;gap:15px;flex-wrap:wrap;margin-bottom:20px;font-size:0.9em;color:var(--muted);">';
    echo '<span>🕐 ' . $generatedAt . '</span>';
    echo '<span>🤖 ' . $modelUsed . '</span>';
    if (isset($report['gene']) && $report['gene']) echo '<span>🧬 Gène: ' . htmlspecialchars($report['gene']) . '</span>';
    if (isset($report['variant']) && $report['variant']) echo '<span>🔍 Variant: ' . htmlspecialchars($report['variant']) . '</span>';
    echo '</div>';
    
    echo '<div class="report-card">';
    echo '<pre>' . $content . '</pre>';
    echo '</div>';
    
    echo '<div style="margin-top:20px;">';
    echo '<a href="?page=chat" class="btn">← Retour au chat</a>';
    echo '<button class="btn" onclick="navigator.clipboard.writeText(document.querySelector(\'pre\').innerText)" style="margin-left:10px;">📋 Copier</button>';
    echo '</div>';
}

// ============================================================================
// 📄 INDEX DES RAPPORTS
// ============================================================================
function render_reports_index() {
    $reportsDir = __DIR__ . '/data/reports';
    $reports = [];
    
    if (is_dir($reportsDir)) {
        $files = array_filter(scandir($reportsDir), function($f) { return str_ends_with($f, '.json'); });
        rsort($files);
        foreach ($files as $file) {
            $rpt = load_json("$reportsDir/$file");
            if ($rpt) $reports[] = $rpt;
        }
    }
    
    echo '<h2 style="margin-bottom:20px;">📄 Tous les Rapports</h2>';
    
    if (empty($reports)) {
        echo '<p style="color:var(--muted);margin:20px 0;">Aucun rapport généré.</p>';
        echo '<a href="?page=chat" class="btn">Poser une question</a>';
        return;
    }
    
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:15px;">';
    
    foreach ($reports as $rpt) {
        $topic = htmlspecialchars($rpt['topic']);
        $date = date('d/m/Y H:i', strtotime($rpt['generated_at']));
        $preview = htmlspecialchars(mb_strimwidth(strip_tags($rpt['content'] ?? ''), 0, 150, '…'));
        
        echo '<div class="report-card">';
        echo '<h4>' . $topic . '</h4>';
        echo '<p style="color:var(--muted);font-size:0.9em;margin:8px 0;">' . $date . '</p>';
        echo '<p style="font-size:0.95em;">' . $preview . '</p>';
        echo '<div style="margin-top:10px;">';
        echo '<a href="?page=report&id=' . htmlspecialchars($rpt['id']) . '" class="btn" style="padding:6px 12px;font-size:0.85em;">Lire</a>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
}

// ============================================================================
// ⚙️ PAGE RÉGLAGES
// ============================================================================
function render_settings_page() {
    $config = load_json(__DIR__ . '/data/config/config.json', []);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        $newConfig = [
            'version' => '2.0.0',
            'installed_at' => $config['installed_at'] ?? date('c'),
            'active_model' => $_POST['model'] ?? DEFAULT_MODEL,
            'auto_improve' => isset($_POST['auto_improve']),
            'cache_ttl' => intval($_POST['cache_ttl'] ?? 3600),
            'language' => $_POST['language'] ?? 'fr'
        ];
        save_json(__DIR__ . '/data/config/config.json', $newConfig);
        echo '<div class="disclaimer" style="border-color:var(--success);background:#00ff8811;">✅ Réglages sauvegardés</div>';
        $config = $newConfig;
    }
    
    $models = [
        'open-mistral-7b' => 'Open Mistral 7B (rapide)',
        'open-mixtral-8x7b' => 'Open Mixtral 8x7B (puissant)',
        'codestral-latest' => 'Codestral (code)'
    ];
    
    $currentModel = $config['active_model'] ?? DEFAULT_MODEL;
    $autoImproveChecked = ($config['auto_improve'] ?? false) ? 'checked' : '';
    
    echo '<h2 style="margin-bottom:20px;">⚙️ Réglages</h2>';
    
    echo '<div class="report-card" style="margin:20px 0;">';
    echo '<form method="post">';
    echo '<div style="margin-bottom:15px;">';
    echo '<label style="display:block;margin-bottom:5px;">Modèle Mistral</label>';
    echo '<select name="model" style="width:100%;padding:10px;background:#0f0f1a;border:1px solid #333;border-radius:5px;color:var(--text);">';
    foreach ($models as $id => $label) {
        $selected = ($currentModel === $id) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($id) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    echo '<div style="margin-bottom:15px;">';
    echo '<label><input type="checkbox" name="auto_improve" value="1" ' . $autoImproveChecked . '> Activer auto-amélioration IA</label>';
    echo '<p style="color:var(--muted);font-size:0.85em;margin-top:5px;">L\'IA analyse ses erreurs et propose des optimisations.</p>';
    echo '</div>';
    
    echo '<button type="submit" name="save_settings" class="btn">💾 Sauvegarder</button>';
    echo '</form>';
    echo '</div>';
    
    echo '<div class="disclaimer">';
    echo '<strong>🔑 Clés API</strong> : 3 clés Mistral en rotation automatique. Free tier = ~20€ de crédits.';
    echo '</div>';
}

// ============================================================================
// 🎯 ROUTEUR PRINCIPAL
// ============================================================================
function run_app() {
    init_structure();
    
    // Traitement soumission question
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question'])) {
        $question = trim($_POST['question']);
        $sessionId = session_id();
        
        // Sauvegarder la question utilisateur
        save_chat_message($sessionId, 'user', $question);
        
        // 1. Analyser la question
        $analysis = analyze_question($question);
        
        // 2. Générer le rapport selon l'intent
        $topic = $analysis['topic'] ?? $question;
        $gene = isset($analysis['gene']) ? $analysis['gene'] : null;
        $variant = isset($analysis['variant']) ? $analysis['variant'] : null;
        
        $result = generate_report($topic, $gene, $variant);
        
        if ($result['success']) {
            // Sauvegarder réponse avec lien vers rapport
            $responseText = "✅ Rapport généré avec succès !\n\n";
            $responseText .= "📊 Analyse : " . ($analysis['intent'] ?? 'general') . "\n";
            if ($gene) $responseText .= "🧬 Gène identifié : $gene\n";
            if ($variant) $responseText .= "🔍 Variant identifié : $variant\n";
            $responseText .= "\n📄 Cliquez ci-dessous pour voir le rapport complet.";
            
            save_chat_message($sessionId, 'assistant', $responseText, $result['report_id']);
            
            // Redirection vers le rapport
            header("Location: ?page=report&id=" . $result['report_id']);
            exit;
        } else {
            save_chat_message($sessionId, 'assistant', "❌ Erreur : " . $result['error']);
        }
    }
    
    // Routage pages
    $page = $_GET['page'] ?? 'chat';
    
    render_header('SciSynth');
    
    if ($page === 'chat') {
        render_chat_page();
    } elseif ($page === 'report' && isset($_GET['id'])) {
        render_report_page($_GET['id']);
    } elseif ($page === 'reports') {
        render_reports_index();
    } elseif ($page === 'settings') {
        render_settings_page();
    } else {
        echo '<div class="disclaimer">❌ Page non trouvée</div>';
        echo '<a href="?page=chat" class="btn">← Accueil</a>';
    }
    
    render_footer();
}

// ============================================================================
// 🚀 POINT D'ENTRÉE
// ============================================================================
run_app();
