<?php
/**
 * QuizMusic - Page de résultats (sécurisée)
 * - Évite la division par zéro
 * - Valeurs par défaut si données manquantes
 * - Barre de progression clampée 0–100
 */

session_start();

// 📚 Vérification de connexion
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 📚 Vérification qu'un quiz a bien été joué
if (!isset($_SESSION['dernier_score'], $_SESSION['total_questions'], $_SESSION['dernier_theme'])) {
    header('Location: index.php');
    exit;
}

// 📚 Récupération des données de résultat (avec cast/valeurs sûres)
$score  = max(0, (int)($_SESSION['dernier_score'] ?? 0));
$total  = max(0, (int)($_SESSION['total_questions'] ?? 0));
$theme  = (string)($_SESSION['dernier_theme'] ?? '');
$pseudo = (string)($_SESSION['user_pseudo'] ?? 'Joueur');

// ✅ Pourcentage sécurisé (pas de division par zéro)
$pourcentage = $total > 0 ? round(($score / $total) * 100, 2) : 0;
// clamp pour l'affichage CSS
$pourcentage_clamp = max(0, min(100, $pourcentage));

// 📚 Système de niveaux
$niveaux = [
    ['min' => 0,  'max' => 2,  'titre' => '🔇 Débutant', 'badge' => 'Mélomane en herbe',  'couleur' => 'from-gray-400 to-gray-600',   'message' => 'C\'est un début ! La musique n\'a pas encore de secrets pour vous, mais c\'est le moment d\'ouvrir grand vos oreilles !'],
    ['min' => 3,  'max' => 4,  'titre' => '🎵 Amateur',  'badge' => 'Auditeur curieux',    'couleur' => 'from-blue-400 to-blue-600',   'message' => 'Pas mal ! Vous commencez à reconnaître quelques classiques. Continuez à explorer !'],
    ['min' => 5,  'max' => 6,  'titre' => '🎶 Confirmé', 'badge' => 'Mélomane averti',     'couleur' => 'from-purple-400 to-purple-600','message' => 'Bravo ! Vous avez de bonnes bases musicales. Votre culture s\'étend bien !'],
    ['min' => 7,  'max' => 8,  'titre' => '🎸 Expert',   'badge' => 'Connaisseur',          'couleur' => 'from-orange-400 to-orange-600','message' => 'Impressionnant ! Vous maîtrisez vraiment votre sujet. Peu de choses vous échappent !'],
    ['min' => 9,  'max' => 10, 'titre' => '🏆 Maître',   'badge' => 'Virtuose musical',     'couleur' => 'from-yellow-400 to-yellow-600','message' => 'Exceptionnel ! Vous êtes un véritable expert. Bravo pour cette performance parfaite !'],
];

// 📚 Recherche du niveau correspondant au score (fallback si rien ne matche)
$niveauActuel = null;
foreach ($niveaux as $n) {
    if ($score >= $n['min'] && $score <= $n['max']) {
        $niveauActuel = $n;
        break;
    }
}
if (!$niveauActuel) { // sécurité si barème changé
    $niveauActuel = $niveaux[0];
}

// 📚 Chargement des informations du thème depuis la BDD (avec fallback)
require_once 'classes/Database.php';
$pdo = Database::getConnexion();
$stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE code = ?");
$stmt->execute([$theme]);
$themeInfo = $stmt->fetch() ?: [
    'titre'   => ucfirst(str_replace('_',' ', $theme ?: 'Thème')),
    'emoji'   => '🎵',
    'couleur' => 'from-indigo-500 to-purple-600'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats - QuizMusic 🎵</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeInScale { from { opacity:0; transform:scale(0.8);} to { opacity:1; transform:scale(1);} }
        @keyframes bounceIn { 0% {transform:scale(0); opacity:0;} 50% {transform:scale(1.1);} 100% {transform:scale(1); opacity:1;} }
        .animate-fade-scale { animation: fadeInScale 0.8s ease-out; }
        .animate-bounce-in { animation: bounceIn 1s ease-out; }
        @keyframes progressBar { from { width:0%; } to { width: var(--progress-width); } }
        .progress-animated { animation: progressBar 2s ease-out forwards; }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">

        <!-- Carte de résultat principale -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden animate-fade-scale">

            <!-- En-tête avec gradient du thème -->
            <div class="bg-gradient-to-r <?= $themeInfo['couleur']; ?> p-8 text-white text-center">
                <div class="text-6xl mb-4"><?= htmlspecialchars($themeInfo['emoji']); ?></div>
                <h1 class="text-3xl font-bold mb-2">Quiz <?= htmlspecialchars($themeInfo['titre']); ?></h1>
                <p class="text-white/80">Résultats de <?= htmlspecialchars($pseudo); ?></p>
            </div>

            <!-- Score principal -->
            <div class="p-8 text-center">

                <!-- Badge du niveau avec animation -->
                <div class="inline-block animate-bounce-in mb-6">
                    <div class="bg-gradient-to-r <?= $niveauActuel['couleur']; ?> text-white px-8 py-4 rounded-2xl shadow-xl">
                        <div class="text-5xl mb-2"><?= $niveauActuel['titre']; ?></div>
                        <div class="text-xl font-semibold"><?= $niveauActuel['badge']; ?></div>
                    </div>
                </div>

                <!-- Score numérique -->
                <div class="text-7xl font-bold text-gray-800 mb-4">
                    <?= $score; ?> / <?= $total; ?>
                </div>

                <!-- Pourcentage -->
                <div class="text-2xl text-gray-600 mb-6">
                    <?= round($pourcentage); ?>% de réussite
                </div>

                <!-- Barre de progression visuelle -->
                <div class="max-w-md mx-auto mb-8">
                    <div class="bg-gray-200 rounded-full h-6 overflow-hidden">
                        <div class="progress-animated h-full bg-gradient-to-r <?= $niveauActuel['couleur']; ?> rounded-full flex items-center justify-end pr-3"
                             style="--progress-width: <?= $pourcentage_clamp; ?>%;">
                            <span class="text-white text-sm font-bold"><?= round($pourcentage); ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- Message personnalisé -->
                <div class="bg-purple-50 border-2 border-purple-200 rounded-2xl p-6 mb-8">
                    <p class="text-lg text-gray-700 leading-relaxed">
                        <?= $niveauActuel['message']; ?>
                    </p>
                </div>

                <!-- Boutons d'action -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="quiz.php?theme=<?= urlencode($theme); ?>"
                       class="bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg">
                        🔄 Rejouer
                    </a>
                    <a href="historique.php"
                       class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg">
                        📊 Mon historique
                    </a>
                    <a href="index.php"
                       class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg">
                        🏠 Accueil
                    </a>
                </div>
            </div>
        </div>

        <!-- Système de niveaux -->
        <div class="mt-8 bg-white/10 backdrop-blur-sm rounded-2xl p-6">
            <h2 class="text-2xl font-bold text-white text-center mb-6">🎯 Système de niveaux</h2>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <?php foreach ($niveaux as $n): ?>
                    <div class="bg-white/20 backdrop-blur-sm rounded-xl p-4 text-center <?= ($n === $niveauActuel) ? 'ring-4 ring-yellow-400' : ''; ?>">
                        <div class="text-2xl mb-2"><?= htmlspecialchars(explode(' ', $n['titre'])[0]); ?></div>
                        <div class="text-white text-sm font-medium"><?= htmlspecialchars(explode(' ', $n['titre'], 2)[1] ?? ''); ?></div>
                        <div class="text-purple-200 text-xs mt-2"><?= $n['min']; ?>–<?= $n['max']; ?> pts</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Confettis simples si score >= 7
        <?php if ($score >= 7): ?>
        function createConfetti() {
            const c = document.createElement('div');
            c.style.position = 'fixed';
            c.style.width = '10px';
            c.style.height = '10px';
            c.style.backgroundColor = ['#ff0000','#00ff00','#0000ff','#ffff00','#ff00ff'][Math.floor(Math.random()*5)];
            c.style.left = Math.random()*100 + '%';
            c.style.top = '-10px';
            c.style.borderRadius = '50%';
            c.style.zIndex = '9999';
            document.body.appendChild(c);
            let pos = -10;
            const fall = setInterval(() => {
                if (pos >= window.innerHeight) { clearInterval(fall); c.remove(); }
                else { pos += 5; c.style.top = pos + 'px'; }
            }, 50);
        }
        for (let i = 0; i < 50; i++) setTimeout(createConfetti, i * 100);
        <?php endif; ?>
    </script>
</body>
</html>
