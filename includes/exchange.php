<?php
// Business logic for requests, matches, sessions, reviews and messaging rules.
// Every function checks that the acting user is allowed to do the thing.

require_once __DIR__ . '/matching.php';

function result(bool $ok, string $message, array $extra = []): array
{
    return ['ok' => $ok, 'message' => $message] + $extra;
}

function is_matched(int $a, int $b): bool
{
    return (bool) fetch_value(
        "SELECT 1 FROM matches WHERE status <> 'cancelled' AND ((user_a = ? AND user_b = ?) OR (user_a = ? AND user_b = ?)) LIMIT 1",
        [$a, $b, $b, $a]
    );
}

/** Students can chat once matched, or when the receiver allows messages from anyone. */
function can_message(int $from, int $to): bool
{
    if ($from === $to) {
        return false;
    }
    $target = fetch_one("SELECT message_permission FROM users WHERE id = ? AND status = 'active'", [$to]);
    if (!$target) {
        return false;
    }
    return $target['message_permission'] === 'everyone' || is_matched($from, $to);
}

// ---------------------------------------------------------------- requests

function create_request(int $senderId, array $in): array
{
    $receiverId = (int) ($in['receiver_id'] ?? 0);
    $wantId     = (int) ($in['want_skill_id'] ?? 0);
    $offerId    = (int) ($in['offer_skill_id'] ?? 0) ?: null;
    $message    = mb_substr(trim((string) ($in['message'] ?? '')), 0, 600);
    $method     = array_key_exists($in['method'] ?? '', METHODS) ? $in['method'] : 'any';
    $date       = trim((string) ($in['preferred_date'] ?? '')) ?: null;
    $time       = trim((string) ($in['preferred_time'] ?? '')) ?: null;

    if ($receiverId === $senderId) {
        return result(false, 'You cannot send a request to yourself.');
    }
    $receiver = fetch_one("SELECT id, name FROM users WHERE id = ? AND status = 'active' AND role = 'student'", [$receiverId]);
    if (!$receiver) {
        return result(false, 'That student could not be found.');
    }
    if (!fetch_value("SELECT 1 FROM user_skills WHERE user_id = ? AND skill_id = ? AND type = 'offer'", [$receiverId, $wantId])) {
        return result(false, 'That student does not offer the skill you selected.');
    }
    if ($offerId && !fetch_value("SELECT 1 FROM user_skills WHERE user_id = ? AND skill_id = ? AND type = 'offer'", [$senderId, $offerId])) {
        return result(false, 'Choose a skill you have listed under "Skills I can teach".');
    }
    if ($date && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d'))) {
        return result(false, 'Preferred date must be today or later.');
    }
    if ($time && !preg_match('/^\d{2}:\d{2}$/', $time)) {
        return result(false, 'Preferred time is not valid.');
    }
    if (fetch_value("SELECT 1 FROM requests WHERE sender_id = ? AND receiver_id = ? AND want_skill_id = ? AND status = 'pending'", [$senderId, $receiverId, $wantId])) {
        return result(false, 'You already have a pending request for this skill with ' . $receiver['name'] . '.');
    }

    q('INSERT INTO requests (sender_id, receiver_id, want_skill_id, offer_skill_id, message, method, preferred_date, preferred_time) VALUES (?,?,?,?,?,?,?,?)',
      [$senderId, $receiverId, $wantId, $offerId, $message ?: null, $method, $date, $time]);
    $id = insert_id();

    $sender = fetch_one('SELECT name FROM users WHERE id = ?', [$senderId]);
    $skill  = fetch_value('SELECT name FROM skills WHERE id = ?', [$wantId]);
    notify($receiverId, 'request', 'New exchange request', $sender['name'] . ' would like to learn ' . $skill . ' from you.', url('requests.php?tab=received'));

    return result(true, 'Request sent to ' . $receiver['name'] . '.', ['id' => $id]);
}

/** $action: accept | reject | cancel */
function respond_request(int $requestId, int $userId, string $action): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $req = fetch_one('SELECT * FROM requests WHERE id = ? FOR UPDATE', [$requestId]);
        if (!$req || $req['status'] !== 'pending') {
            $pdo->rollBack();
            return result(false, 'This request is no longer pending.');
        }

        if ($action === 'cancel') {
            if ((int) $req['sender_id'] !== $userId) {
                $pdo->rollBack();
                return result(false, 'You can only cancel your own requests.');
            }
            q("UPDATE requests SET status = 'cancelled', responded_at = NOW() WHERE id = ?", [$requestId]);
            $pdo->commit();
            return result(true, 'Request cancelled.');
        }

        if ((int) $req['receiver_id'] !== $userId || !in_array($action, ['accept', 'reject'], true)) {
            $pdo->rollBack();
            return result(false, 'You cannot respond to this request.');
        }

        $me = fetch_value('SELECT name FROM users WHERE id = ?', [$userId]);
        $skill = fetch_value('SELECT name FROM skills WHERE id = ?', [$req['want_skill_id']]);

        if ($action === 'reject') {
            q("UPDATE requests SET status = 'rejected', responded_at = NOW() WHERE id = ?", [$requestId]);
            notify((int) $req['sender_id'], 'request_rejected', 'Request declined', $me . ' declined your request for ' . $skill . '.', url('requests.php?tab=sent'));
            $pdo->commit();
            return result(true, 'Request declined.');
        }

        $bundles = load_bundles([$req['sender_id'], $req['receiver_id']]);
        $score = score_pair($bundles[$req['sender_id']], $bundles[$req['receiver_id']])['score'];

        q("UPDATE requests SET status = 'accepted', responded_at = NOW() WHERE id = ?", [$requestId]);
        q('INSERT INTO matches (request_id, user_a, user_b, skill_a_id, skill_b_id, score) VALUES (?,?,?,?,?,?)',
          [$requestId, $req['sender_id'], $req['receiver_id'], $req['want_skill_id'], $req['offer_skill_id'], $score]);
        $matchId = insert_id();
        $pdo->commit();

        notify((int) $req['sender_id'], 'request_accepted', 'Request accepted', $me . ' accepted your request for ' . $skill . '. You can now message each other.', url('matches.php'));
        notify((int) $req['sender_id'], 'match', 'New skill match', 'You matched with ' . $me . ' (' . $score . '% compatible).', url('matches.php'));
        notify((int) $req['receiver_id'], 'match', 'New skill match', 'You matched with ' . fetch_value('SELECT name FROM users WHERE id = ?', [$req['sender_id']]) . '.', url('matches.php'));
        return result(true, 'Request accepted. You are now matched!', ['match_id' => $matchId]);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

// ----------------------------------------------------------------- matches

/** Matches for a user with partner details, skills oriented to the viewer. */
function user_matches(int $userId, ?string $status = null): array
{
    $sql = "SELECT m.*, IF(m.user_a = ?, m.user_b, m.user_a) AS partner_id,
                   p.name AS partner_name, p.programme AS partner_programme, p.year_of_study AS partner_year,
                   sa.name AS skill_a, sb.name AS skill_b,
                   (SELECT COUNT(*) FROM ratings r WHERE r.match_id = m.id AND r.reviewer_id = ?) AS i_reviewed
              FROM matches m
              JOIN users p ON p.id = IF(m.user_a = ?, m.user_b, m.user_a)
              JOIN skills sa ON sa.id = m.skill_a_id
              LEFT JOIN skills sb ON sb.id = m.skill_b_id
             WHERE (m.user_a = ? OR m.user_b = ?)" . ($status ? ' AND m.status = ?' : '') . '
             ORDER BY m.created_at DESC';
    $params = [$userId, $userId, $userId, $userId, $userId];
    if ($status) {
        $params[] = $status;
    }
    $rows = fetch_all($sql, $params);
    foreach ($rows as &$r) {
        $iAmA = (int) $r['user_a'] === $userId;
        $r['i_learn'] = $iAmA ? $r['skill_a'] : $r['skill_b'];
        $r['i_teach'] = $iAmA ? $r['skill_b'] : $r['skill_a'];
    }
    return $rows;
}

function get_user_match(int $matchId, int $userId): ?array
{
    return fetch_one('SELECT * FROM matches WHERE id = ? AND (user_a = ? OR user_b = ?)', [$matchId, $userId, $userId]);
}

function partner_of(array $match, int $userId): int
{
    return (int) ($match['user_a'] == $userId ? $match['user_b'] : $match['user_a']);
}

/** $action: complete | end */
function change_match(int $matchId, int $userId, string $action): array
{
    $match = get_user_match($matchId, $userId);
    if (!$match || $match['status'] !== 'active') {
        return result(false, 'This exchange is no longer active.');
    }
    $partner = partner_of($match, $userId);
    $me = fetch_value('SELECT name FROM users WHERE id = ?', [$userId]);

    if ($action === 'complete') {
        q("UPDATE matches SET status = 'completed' WHERE id = ?", [$matchId]);
        q("UPDATE requests SET status = 'completed' WHERE id = ?", [$match['request_id']]);
        notify($partner, 'session_completed', 'Exchange completed', $me . ' marked your exchange as completed. Leave a review!', url('reviews.php'));
        return result(true, 'Exchange marked as completed. Don\'t forget to leave a review.');
    }
    if ($action === 'end') {
        q("UPDATE matches SET status = 'cancelled' WHERE id = ?", [$matchId]);
        q("UPDATE requests SET status = 'cancelled' WHERE id = ?", [$match['request_id']]);
        q("UPDATE sessions SET status = 'cancelled' WHERE match_id = ? AND status = 'scheduled'", [$matchId]);
        notify($partner, 'session', 'Match ended', $me . ' ended your skill exchange.', url('matches.php'));
        return result(true, 'Match ended.');
    }
    return result(false, 'Unknown action.');
}

// ---------------------------------------------------------------- sessions

function mark_missed_sessions(): void
{
    q("UPDATE sessions SET status = 'missed' WHERE status = 'scheduled' AND TIMESTAMP(session_date, end_time) < (NOW() - INTERVAL 1 DAY)");
}

/** Validate and normalise session form input. Returns the cleaned fields, or an error string. */
function session_fields(array $in, array $match): array|string
{
    $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 150);
    $date  = (string) ($in['session_date'] ?? '');
    $start = (string) ($in['start_time'] ?? '');
    $end   = (string) ($in['end_time'] ?? '');
    $loc   = mb_substr(trim((string) ($in['location'] ?? '')), 0, 150) ?: null;
    $link  = mb_substr(trim((string) ($in['meeting_link'] ?? '')), 0, 255) ?: null;
    $notes = mb_substr(trim((string) ($in['notes'] ?? '')), 0, 500) ?: null;
    $skill = (int) ($in['skill_id'] ?? 0) ?: null;

    if ($title === '') {
        return 'Give the session a title.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
        return 'Choose a date that is today or later.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $end <= $start) {
        return 'The end time must be after the start time.';
    }
    if ($link && !preg_match('#^https?://#i', $link)) {
        return 'The meeting link must start with http:// or https://';
    }
    if ($skill && !in_array($skill, [(int) $match['skill_a_id'], (int) $match['skill_b_id']], true)) {
        $skill = null;
    }
    return compact('title', 'date', 'start', 'end', 'loc', 'link', 'notes', 'skill');
}

function create_session(int $userId, array $in): array
{
    $match = get_user_match((int) ($in['match_id'] ?? 0), $userId);
    if (!$match || $match['status'] !== 'active') {
        return result(false, 'Choose one of your active matches.');
    }
    $f = session_fields($in, $match);
    if (is_string($f)) {
        return result(false, $f);
    }

    q('INSERT INTO sessions (match_id, skill_id, created_by, title, session_date, start_time, end_time, location, meeting_link, notes) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$match['id'], $f['skill'], $userId, $f['title'], $f['date'], $f['start'], $f['end'], $f['loc'], $f['link'], $f['notes']]);

    $me = fetch_value('SELECT name FROM users WHERE id = ?', [$userId]);
    notify(partner_of($match, $userId), 'session', 'Session scheduled', $me . ' scheduled "' . $f['title'] . '" on ' . date('M j', strtotime($f['date'])) . ' at ' . substr($f['start'], 0, 5) . '.', url('sessions.php'));
    return result(true, 'Session scheduled.');
}

/** Edit a scheduled session. Either person in the match may reschedule it. */
function update_session(int $sessionId, int $userId, array $in): array
{
    $s = fetch_one('SELECT s.*, m.user_a, m.user_b, m.skill_a_id, m.skill_b_id FROM sessions s JOIN matches m ON m.id = s.match_id WHERE s.id = ? AND (m.user_a = ? OR m.user_b = ?)', [$sessionId, $userId, $userId]);
    if (!$s || $s['status'] !== 'scheduled') {
        return result(false, 'Only scheduled sessions can be edited.');
    }
    $f = session_fields($in, $s);
    if (is_string($f)) {
        return result(false, $f);
    }
    q('UPDATE sessions SET skill_id = ?, title = ?, session_date = ?, start_time = ?, end_time = ?, location = ?, meeting_link = ?, notes = ? WHERE id = ?',
      [$f['skill'], $f['title'], $f['date'], $f['start'], $f['end'], $f['loc'], $f['link'], $f['notes'], $sessionId]);

    $me = fetch_value('SELECT name FROM users WHERE id = ?', [$userId]);
    notify(partner_of($s, $userId), 'session', 'Session updated', $me . ' updated "' . $f['title'] . '": now ' . date('M j', strtotime($f['date'])) . ' at ' . substr($f['start'], 0, 5) . '.', url('sessions.php'));
    return result(true, 'Session updated.');
}

/** Delete a session. Only the person who created it can remove it. */
function delete_session(int $sessionId, int $userId): array
{
    $s = fetch_one('SELECT s.*, m.user_a, m.user_b FROM sessions s JOIN matches m ON m.id = s.match_id WHERE s.id = ? AND s.created_by = ?', [$sessionId, $userId]);
    if (!$s) {
        return result(false, 'Only the person who scheduled a session can delete it.');
    }
    q('DELETE FROM sessions WHERE id = ?', [$sessionId]);
    if ($s['status'] === 'scheduled') {
        $me = fetch_value('SELECT name FROM users WHERE id = ?', [$userId]);
        notify(partner_of($s, $userId), 'session', 'Session removed', $me . ' removed "' . $s['title'] . '" from your schedule.', url('sessions.php'));
    }
    return result(true, 'Session deleted.');
}

/** $status: completed | cancelled */
function change_session(int $sessionId, int $userId, string $status): array
{
    $s = fetch_one('SELECT s.*, m.user_a, m.user_b FROM sessions s JOIN matches m ON m.id = s.match_id WHERE s.id = ? AND (m.user_a = ? OR m.user_b = ?)', [$sessionId, $userId, $userId]);
    if (!$s || $s['status'] !== 'scheduled') {
        return result(false, 'This session can no longer be changed.');
    }
    if ($status === 'completed' && $s['session_date'] > date('Y-m-d')) {
        return result(false, 'You can only complete a session on or after its date.');
    }
    if (!in_array($status, ['completed', 'cancelled'], true)) {
        return result(false, 'Unknown status.');
    }
    q('UPDATE sessions SET status = ? WHERE id = ?', [$status, $sessionId]);
    $partner = (int) ($s['user_a'] == $userId ? $s['user_b'] : $s['user_a']);
    $me = fetch_value('SELECT name FROM users WHERE id = ?', [$userId]);
    notify($partner, $status === 'completed' ? 'session_completed' : 'session',
           $status === 'completed' ? 'Session completed' : 'Session cancelled',
           $me . ' ' . $status . ' "' . $s['title'] . '".', url('sessions.php'));
    return result(true, 'Session ' . $status . '.');
}

// ----------------------------------------------------------------- reviews

function submit_review(int $userId, int $matchId, int $score, string $comment): array
{
    $match = get_user_match($matchId, $userId);
    if (!$match || $match['status'] !== 'completed') {
        return result(false, 'You can only review a completed exchange.');
    }
    if ($score < 1 || $score > 5) {
        return result(false, 'Choose a rating from 1 to 5 stars.');
    }
    if (fetch_value('SELECT 1 FROM ratings WHERE match_id = ? AND reviewer_id = ?', [$matchId, $userId])) {
        return result(false, 'You have already reviewed this exchange.');
    }
    $partner = partner_of($match, $userId);
    try {
        q('INSERT INTO ratings (match_id, reviewer_id, reviewee_id, score, comment) VALUES (?,?,?,?,?)',
          [$matchId, $userId, $partner, $score, mb_substr(trim($comment), 0, 600) ?: null]);
    } catch (PDOException $ex) {
        return result(false, 'You have already reviewed this exchange.');   // unique key race
    }
    $me = fetch_value('SELECT name FROM users WHERE id = ?', [$userId]);
    notify($partner, 'review', 'New review', $me . ' gave you ' . $score . ' star' . ($score > 1 ? 's' : '') . '.', url('reviews.php?tab=received'));
    return result(true, 'Thanks — your review has been submitted.');
}

/** Edit a review you wrote. Reviews an admin has hidden cannot be changed. */
function update_review(int $userId, int $ratingId, int $score, string $comment): array
{
    $r = fetch_one('SELECT * FROM ratings WHERE id = ? AND reviewer_id = ?', [$ratingId, $userId]);
    if (!$r) {
        return result(false, 'Review not found.');
    }
    if ($r['is_hidden']) {
        return result(false, 'This review was removed by an admin and cannot be edited.');
    }
    if ($score < 1 || $score > 5) {
        return result(false, 'Choose a rating from 1 to 5 stars.');
    }
    q('UPDATE ratings SET score = ?, comment = ? WHERE id = ?', [$score, mb_substr(trim($comment), 0, 600) ?: null, $ratingId]);
    return result(true, 'Review updated.');
}

/** Delete a review you wrote. The exchange goes back to "to review". */
function delete_review(int $userId, int $ratingId): array
{
    if (!fetch_value('SELECT 1 FROM ratings WHERE id = ? AND reviewer_id = ?', [$ratingId, $userId])) {
        return result(false, 'Review not found.');
    }
    q('DELETE FROM ratings WHERE id = ?', [$ratingId]);
    return result(true, 'Review deleted.');
}

/** Rating, exchange counts for a profile. */
function user_stats(int $userId): array
{
    $r = fetch_one("SELECT AVG(score) AS avg_rating, COUNT(*) AS reviews FROM ratings WHERE reviewee_id = ? AND is_hidden = 0", [$userId]);
    $m = fetch_one("SELECT COUNT(*) AS total, SUM(status = 'completed') AS completed FROM matches WHERE (user_a = ? OR user_b = ?) AND status <> 'cancelled'", [$userId, $userId]);
    return [
        'avg_rating' => $r['reviews'] ? (float) $r['avg_rating'] : null,
        'reviews'    => (int) $r['reviews'],
        'exchanges'  => (int) $m['total'],
        'completed'  => (int) $m['completed'],
    ];
}

// ---------------------------------------------------------------- messages

/** Latest message per conversation partner, newest first, with unread counts. */
function conversations(int $userId, int $limit = 50): array
{
    return fetch_all(
        "SELECT m.id, m.body, m.sender_id, m.created_at, t.pid AS partner_id, u.name AS partner_name,
                (SELECT COUNT(*) FROM messages x WHERE x.sender_id = t.pid AND x.receiver_id = ? AND x.is_read = 0) AS unread
           FROM messages m
           JOIN (SELECT IF(sender_id = ?, receiver_id, sender_id) AS pid, MAX(id) AS mid
                   FROM messages WHERE sender_id = ? OR receiver_id = ? GROUP BY pid) t ON t.mid = m.id
           JOIN users u ON u.id = t.pid
          ORDER BY m.id DESC LIMIT " . (int) $limit,
        [$userId, $userId, $userId, $userId]
    );
}

function send_message(int $from, int $to, string $body): array
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 2000) {
        return result(false, 'Write a message of up to 2000 characters.');
    }
    if (!can_message($from, $to)) {
        return result(false, 'You can message this student once your exchange request is accepted.');
    }
    q('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?,?,?)', [$from, $to, $body]);
    $id = insert_id();
    $me = fetch_value('SELECT name FROM users WHERE id = ?', [$from]);
    // One unread "new message" notification per sender at a time, to avoid flooding.
    if (!fetch_value("SELECT 1 FROM notifications WHERE user_id = ? AND type = 'message' AND is_read = 0 AND link = ?", [$to, 'messages.php?with=' . $from])) {
        notify($to, 'message', 'New message from ' . $me, mb_substr($body, 0, 80), url('messages.php?with=' . $from));
    }
    return result(true, 'Sent.', ['id' => $id]);
}
