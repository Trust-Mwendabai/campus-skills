<?php
// OPTIONAL demo data for development and screenshots. Never run this on a real deployment.
//
//   php database/seed-demo.php --yes
//
// Every demo account uses the password  Demo1234  (students) — and the admins use  Admin1234.

if (PHP_SAPI !== 'cli') {
    exit('Run this script from the command line.');
}
if (!in_array('--yes', $argv, true)) {
    exit("This inserts fake demo users into database '" . (getenv('DB_NAME') ?: 'campus_skill_exchange') . "'.\nRe-run with --yes to continue.\n");
}
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/exchange.php';

if (fetch_value("SELECT COUNT(*) FROM users WHERE email LIKE '%@demo.skillx'")) {
    exit("Demo data already exists.\n");
}

$hash = password_hash('Demo1234', PASSWORD_DEFAULT);
$adminHash = password_hash('Admin1234', PASSWORD_DEFAULT);

// name, programme, faculty, year, method
$people = [
    ['Trust Mwendabai', 'Information Security', 'School of Computing', 3, 'any'],
    ['John Banda', 'Computer Science', 'School of Computing', 3, 'online'],
    ['Mary Phiri', 'Graphic Design', 'School of Arts', 2, 'physical'],
    ['Peter Zulu', 'Business Administration', 'School of Business', 4, 'any'],
    ['Amara Okafor', 'Data Science', 'School of Computing', 2, 'any'],
    ['Brian Tembo', 'Software Engineering', 'School of Computing', 1, 'online'],
    ['Chanda Mulenga', 'Music', 'School of Arts', 3, 'one_to_one'],
    ['Esther Moyo', 'Statistics', 'School of Science', 2, 'any'],
];
$ids = [];
foreach ($people as $i => [$name, $prog, $fac, $year, $method]) {
    q('INSERT INTO users (student_id, name, email, phone, programme, faculty, year_of_study, password_hash, bio, learning_method) VALUES (?,?,?,?,?,?,?,?,?,?)',
      ['DEMO-' . (1001 + $i), $name, strtolower(str_replace(' ', '.', $name)) . '@demo.skillx', '+26097000' . (1000 + $i), $prog, $fac, $year, $hash,
       'Studying ' . $prog . '. Happy to swap skills and learn from classmates.', $method]);
    $ids[$name] = insert_id();
}
q("INSERT INTO users (student_id, name, email, password_hash, role) VALUES ('DEMO-ADMIN', 'Demo Admin', 'admin@demo.skillx', ?, 'admin'), ('DEMO-SUPER', 'Demo Super Admin', 'super@demo.skillx', ?, 'super_admin')", [$adminHash, $adminHash]);

$skill = fn(string $n) => (int) fetch_value('SELECT id FROM skills WHERE name = ?', [$n]);
$give = function (string $who, string $type, array $list) use ($ids, $skill) {
    foreach ($list as $name => $level) {
        q('INSERT INTO user_skills (user_id, skill_id, type, level, experience) VALUES (?,?,?,?,?)', [$ids[$who], $skill($name), $type, $level, $type === 'offer' ? '2 years' : null]);
    }
};
$give('Trust Mwendabai', 'offer', ['Python' => 'advanced', 'Ethical hacking' => 'advanced', 'Networking' => 'intermediate']);
$give('Trust Mwendabai', 'want',  ['Graphic design' => 'beginner', 'UI/UX design' => 'beginner', 'Video editing' => 'beginner']);
$give('John Banda', 'offer', ['Python' => 'advanced', 'Java' => 'advanced', 'Networking' => 'intermediate']);
$give('John Banda', 'want',  ['UI/UX design' => 'beginner', 'Public speaking' => 'beginner']);
$give('Mary Phiri', 'offer', ['Graphic design' => 'advanced', 'Adobe Photoshop' => 'advanced', 'Video editing' => 'intermediate']);
$give('Mary Phiri', 'want',  ['Python' => 'beginner', 'Excel' => 'beginner']);
$give('Peter Zulu', 'offer', ['Excel' => 'advanced', 'Pitching' => 'intermediate', 'Public speaking' => 'advanced']);
$give('Peter Zulu', 'want',  ['Python' => 'beginner', 'Data analysis' => 'beginner']);
$give('Amara Okafor', 'offer', ['Data analysis' => 'advanced', 'SQL' => 'advanced', 'Statistics' => 'intermediate']);
$give('Amara Okafor', 'want',  ['Guitar' => 'beginner', 'Ethical hacking' => 'beginner']);
$give('Brian Tembo', 'offer', ['JavaScript' => 'intermediate', 'HTML & CSS' => 'advanced']);
$give('Brian Tembo', 'want',  ['Python' => 'beginner', 'Git & GitHub' => 'beginner']);
$give('Chanda Mulenga', 'offer', ['Guitar' => 'advanced', 'Piano' => 'advanced', 'Music theory' => 'advanced']);
$give('Chanda Mulenga', 'want',  ['SQL' => 'beginner', 'Excel' => 'beginner']);
$give('Esther Moyo', 'offer', ['Statistics' => 'advanced', 'Calculus' => 'advanced']);
$give('Esther Moyo', 'want',  ['Python' => 'beginner', 'Public speaking' => 'intermediate']);

// Availability: weekday afternoons for most people
foreach ($ids as $name => $uid) {
    foreach ([1, 3, 5] as $day) {
        q('INSERT INTO availability (user_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)', [$uid, $day, '16:00:00', '18:00:00']);
    }
}

// A completed exchange with a review, an active match with a session, and a pending request
$r = create_request($ids['Trust Mwendabai'], ['receiver_id' => $ids['Mary Phiri'], 'want_skill_id' => $skill('Graphic design'), 'offer_skill_id' => $skill('Python'), 'message' => 'I would love to learn Graphic design from you. I can teach you Python in exchange.']);
respond_request($r['id'], $ids['Mary Phiri'], 'accept');
$matchId = (int) fetch_value('SELECT id FROM matches WHERE request_id = ?', [$r['id']]);
send_message($ids['Mary Phiri'], $ids['Trust Mwendabai'], 'Hi Trust! When would you like to start?');
send_message($ids['Trust Mwendabai'], $ids['Mary Phiri'], 'How about Friday at 16:00?');
create_session($ids['Trust Mwendabai'], ['match_id' => $matchId, 'title' => 'Graphic design basics', 'session_date' => date('Y-m-d', strtotime('+3 days')), 'start_time' => '16:00', 'end_time' => '17:00', 'location' => 'Library, 2nd floor']);

$r2 = create_request($ids['Brian Tembo'], ['receiver_id' => $ids['John Banda'], 'want_skill_id' => $skill('Python'), 'message' => 'Could you teach me Python?']);
respond_request($r2['id'], $ids['John Banda'], 'accept');
$m2 = (int) fetch_value('SELECT id FROM matches WHERE request_id = ?', [$r2['id']]);
change_match($m2, $ids['John Banda'], 'complete');
submit_review($ids['Brian Tembo'], $m2, 5, 'John explained everything clearly and was very patient.');
submit_review($ids['John Banda'], $m2, 4, 'Brian is keen and asks great questions.');

create_request($ids['Amara Okafor'], ['receiver_id' => $ids['Trust Mwendabai'], 'want_skill_id' => $skill('Ethical hacking'), 'offer_skill_id' => $skill('SQL'), 'message' => 'I would like to learn ethical hacking. I can teach you SQL and data analysis.']);

// Moderation samples: a pending sign-up, a suspended student, an open report and an announcement
q("INSERT INTO users (student_id, name, email, programme, faculty, year_of_study, password_hash, status) VALUES
   ('DEMO-2001', 'Grace Lungu', 'grace.lungu@demo.skillx', 'Nursing', 'School of Health', 1, ?, 'pending'),
   ('DEMO-2002', 'Kelvin Sakala', 'kelvin.sakala@demo.skillx', 'Law', 'School of Law', 2, ?, 'suspended')", [$hash, $hash]);
$kelvin = (int) fetch_value("SELECT id FROM users WHERE email = 'kelvin.sakala@demo.skillx'");
q('INSERT INTO reports (reporter_id, reported_user_id, category, description) VALUES (?,?,?,?)',
  [$ids['Esther Moyo'], $kelvin, 'spam', 'Kept sending unrelated promotional links in messages.']);
$adminId = (int) fetch_value("SELECT id FROM users WHERE email = 'admin@demo.skillx'");
q('INSERT INTO announcements (admin_id, title, body) VALUES (?,?,?)',
  [$adminId, 'Welcome to Campus SkillX', 'Add the skills you can teach and the ones you want to learn, then send your first exchange request.']);
q("INSERT INTO notifications (user_id, type, title, body, link) SELECT id, 'announcement', 'Welcome to Campus SkillX', 'Add your skills and send your first exchange request.', ? FROM users WHERE status = 'active' AND role = 'student'", [url('notifications.php')]);

echo "Demo data created.\n";
echo "  Students:  trust.mwendabai@demo.skillx  (password Demo1234)  — also john.banda@, mary.phiri@ ... @demo.skillx\n";
echo "  Admin:     admin@demo.skillx  (password Admin1234)\n";
echo "  Super:     super@demo.skillx  (password Admin1234)\n";
echo "  Also:      grace.lungu@ (pending approval), kelvin.sakala@ (suspended), 1 open report, 1 announcement\n";
