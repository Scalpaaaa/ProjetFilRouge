<?php
// profile.php
session_start();
require_once 'classes/Database.php';

// Sécurité : login obligatoire
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = Database::getConnexion();
$userId = (int) $_SESSION['user_id'];

// ─────────────────────────────────────────────────────────────
// 1) Traitement modification de pseudo
// ─────────────────────────────────────────────────────────────
$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nouveau_pseudo'])) {
    $nouveau = trim($_POST['nouveau_pseudo']);

    if ($nouveau === '') {
        $flash = ['type' => 'error', 'msg' => "Le pseudo ne peut pas être vide."];
    } elseif (mb_strlen($nouveau) < 3 || mb_strlen($nouveau) > 50) {
        $flash = ['type' => 'error', 'msg' => "Le pseudo doit contenir entre 3 et 50 caractères."];
    } else {
        // Vérifier l’unicité
        $sql = "SELECT id FROM users WHERE pseudo = ? AND id <> ?";
        $st = $pdo->prepare($sql);
        $st->execute([$nouveau, $userId]);
        if ($st->fetch()) {
            $flash = ['type' => 'error', 'msg' => "Ce pseudo est déjà utilisé."];
        } else {
            $up = $pdo->prepare("UPDATE users SET pseudo = ? WHERE id = ?");
            $up->execute([$nouveau, $userId]);
            $_SESSION['user_pseudo'] = $nouveau; // garder la barre de nav à jour
            $flash = ['type' => 'success', 'msg' => "Pseudo mis à jour avec succès ✅"];
        }
    }
}

// ─────────────────────────────────────────────────────────────
// 2) Récupération des infos utilisateur
// ─────────────────────────────────────────────────────────────
$user = null;

// created_at est optionnel : si la colonne n'existe pas, on fallback à NULL
try {
    $st = $pdo->prepare("SELECT id, pseudo, email, created_at FROM users WHERE id = ?");
    $st->execute([$userId]);
    $user = $st->fetch();
} catch (\Throwable $e) {
    $st = $pdo->prepare("SELECT id, pseudo, email FROM users WHERE id = ?");
    $st->execute([$userId]);
    $user = $st->fetch();
    $user['created_at'] = null;
}

// ─────────────────────────────────────────────────────────────
// 3) Statistiques : nb de parties, thème préféré
// ─────────────────────────────────────────────────────────────
$nbParties = 0;
$themePref  = null;

$st = $pdo->prepare("SELECT COUNT(*) AS c FROM scores WHERE user_id = ?");
$st->execute([$userId]);
$nbParties = (int) $st->fetch()['c'];

// thème préféré = le plus joué (par questionnaire)
$st = $pdo->prepare("
    SELECT q.titre, q.code, COUNT(*) AS nb
    FROM scores s
    JOIN questionnaires q ON q.id = s.questionnaire_id
    WHERE s.user_id = ?
    GROUP BY s.questionnaire_id
    ORDER BY nb DESC, q.titre ASC
    LIMIT 1
");
$st->execute([$userId]);
$themePref = $st->fetch();

// ─────────────────────────────────────────────────────────────
// 4) Badges obtenus
// ─────────────────────────────────────────────────────────────
$badges = [];
try {
    $st = $pdo->prepare("
        SELECT b.code, b.titre, b.description, IFNULL(b.icone,'🏅') AS icone, ub.obtenu_le
        FROM user_badges ub
        JOIN badges b ON b.id = ub.badge_id
        WHERE ub.user_id = ?
        ORDER BY ub.obtenu_le DESC
    ");
    $st->execute([$userId]);
    $badges = $st->fetchAll();
} catch (\Throwable $e) {
    $badges = []; // si les tables n'existent pas encore
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Mon profil - QuizMusic</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen">
  <div class="container mx-auto px-4 py-8 max-w-5xl">
    <nav class="flex justify-between items-center mb-8">
      <h1 class="text-white text-2xl font-bold">👤 Mon profil</h1>
      <div class="flex gap-3">
        <a href="index.php" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg">🏠 Accueil</a>
        <a href="historique.php" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg">📊 Historique</a>
        <a href="logout.php" class="bg-red-500/80 hover:bg-red-600 text-white px-4 py-2 rounded-lg">🚪 Déconnexion</a>
      </div>
    </nav>

    <?php if ($flash['type']): ?>
      <div class="mb-6 p-4 rounded-xl <?=
        $flash['type']==='success' ? 'bg-green-500/20 text-green-100' : 'bg-red-500/20 text-red-100' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <!-- Carte identité -->
    <section class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 text-white shadow-quiz mb-8">
      <h2 class="text-xl font-semibold mb-4">🔎 Informations</h2>
      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <div class="text-sm opacity-80">Pseudo</div>
          <div class="text-lg font-semibold"><?= htmlspecialchars($user['pseudo']) ?></div>
        </div>
        <div>
          <div class="text-sm opacity-80">Email</div>
          <div class="text-lg"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <div>
          <div class="text-sm opacity-80">Date d’inscription</div>
          <div class="text-lg">
            <?php
              echo $user['created_at']
                ? date('d/m/Y', strtotime($user['created_at']))
                : '—';
            ?>
          </div>
        </div>
      </div>
    </section>

    <!-- Statistiques -->
    <section class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 text-white shadow-quiz mb-8">
      <h2 class="text-xl font-semibold mb-4">📈 Statistiques</h2>
      <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-white/10 rounded-xl p-4">
          <div class="text-3xl font-bold"><?= $nbParties ?></div>
          <div class="opacity-80">Parties jouées</div>
        </div>
        <div class="bg-white/10 rounded-xl p-4 md:col-span-2">
          <div class="opacity-80 mb-1">Thème préféré</div>
          <div class="text-lg font-semibold">
            <?= $themePref ? htmlspecialchars($themePref['titre'])." (".$themePref['nb']."×)" : '—' ?>
          </div>
        </div>
      </div>
    </section>

    <!-- Modifier le pseudo -->
    <section class="bg-white rounded-2xl p-6 shadow-xl mb-8">
      <h2 class="text-gray-800 text-xl font-semibold mb-4">✏️ Modifier mon pseudo</h2>
      <form method="post" class="flex gap-3 items-center">
        <input type="text" name="nouveau_pseudo" required minlength="3" maxlength="50"
               value="<?= htmlspecialchars($user['pseudo']) ?>"
               class="border rounded-lg px-4 py-2 flex-1 focus:ring-2 focus:ring-purple-500">
        <button class="bg-gradient-to-r from-purple-500 to-indigo-500 text-white px-5 py-2 rounded-lg hover:scale-105 transition">
          Mettre à jour
        </button>
      </form>
    </section>

    <!-- Badges -->
    <section class="bg-white rounded-2xl p-6 shadow-xl">
      <h2 class="text-gray-800 text-xl font-semibold mb-4">🏅 Mes badges</h2>
      <?php if (!$badges): ?>
        <p class="text-gray-600">Aucun badge pour le moment. Jouez des parties pour en débloquer !</p>
      <?php else: ?>
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
          <?php foreach ($badges as $b): ?>
            <div class="border rounded-xl p-4 flex items-start gap-3">
              <div class="text-3xl"><?= htmlspecialchars($b['icone']) ?></div>
              <div>
                <div class="font-semibold"><?= htmlspecialchars($b['titre']) ?></div>
                <div class="text-sm text-gray-600"><?= htmlspecialchars($b['description']) ?></div>
                <div class="text-xs text-gray-500 mt-1">
                  Obtenu le <?= date('d/m/Y', strtotime($b['obtenu_le'])) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</body>
</html>
