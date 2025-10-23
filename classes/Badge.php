<?php
require_once 'Database.php';

class Badge
{
    // Donne un badge à l'utilisateur s'il ne l'a pas déjà
    private static function awardIfNotHave(PDO $pdo, int $userId, string $badgeCode): void {
        $bid = self::getBadgeIdByCode($pdo, $badgeCode);
        if (!$bid) return;

        $check = $pdo->prepare("SELECT 1 FROM user_badges WHERE user_id=? AND badge_id=?");
        $check->execute([$userId, $bid]);
        if (!$check->fetch()) {
            $insert = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
            $insert->execute([$userId, $bid]);
        }
    }

    private static function getBadgeIdByCode(PDO $pdo, string $code): ?int {
        $st = $pdo->prepare("SELECT id FROM badges WHERE code=?");
        $st->execute([$code]);
        $row = $st->fetch();
        return $row ? (int)$row['id'] : null;
    }

    // Vérifie les conditions et attribue les badges si besoin
    public static function verifierEtAttribuer(PDO $pdo, int $userId, int $score, int $totalQuestions): void
    {
        // 1️⃣ Premier pas : première partie jouée
        $st = $pdo->prepare("SELECT COUNT(*) AS c FROM scores WHERE user_id=?");
        $st->execute([$userId]);
        if ((int)$st->fetch()['c'] === 1) {
            self::awardIfNotHave($pdo, $userId, 'first_quiz');
        }

        // 2️⃣ Explorateur : a joué tous les thèmes
        $st = $pdo->prepare("SELECT COUNT(DISTINCT questionnaire_id) AS nb FROM scores WHERE user_id=?");
        $st->execute([$userId]);
        $themesJoues = (int)$st->fetch()['nb'];

        $st2 = $pdo->query("SELECT COUNT(*) AS total FROM questionnaires WHERE actif=1");
        $themesTotal = (int)$st2->fetch()['total'];

        if ($themesJoues >= $themesTotal && $themesTotal > 0) {
            self::awardIfNotHave($pdo, $userId, 'explorateur');
        }

        // 3️⃣ Perfectionniste : score parfait
        if ($totalQuestions > 0 && $score === $totalQuestions) {
            self::awardIfNotHave($pdo, $userId, 'perfect');
        }

        // 4️⃣ Marathon : 10 parties en une journée
        $st = $pdo->prepare("
            SELECT COUNT(*) AS nb FROM scores
            WHERE user_id = ? AND DATE(date_jeu) = CURDATE()
        ");
        $st->execute([$userId]);
        if ((int)$st->fetch()['nb'] >= 10) {
            self::awardIfNotHave($pdo, $userId, 'marathon');
        }
    }
}
