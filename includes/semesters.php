<?php
/**
 * A listing's semesters.
 *
 * A listing can run for more than one semester, as long as they are back to
 * back: Summer and Fall 2027, or Fall 2026 and Spring 2027. They live in
 * `sublet_semesters` (sublet_id, semester_code), one row each. A gap means a
 * second listing, posted once the first is over.
 *
 * `sublets.semester` stays as the listing's *first* semester and is kept in
 * step by set_listing_semesters(). Everything that only needs one semester
 * (the activity log's tag on each event, the archive's bookkeeping) keeps
 * reading it, and the code before this table still reads a correct value if a
 * deploy is ever rolled back.
 *
 * Order and adjacency come from the semester's *name*, "Spring 2027", not
 * from semesters.sort_order, which is whatever order the admin added them in.
 * Within a year the terms run Spring, Summer, Fall; Fall is followed by the
 * next Spring (winter break is not a semester). A name that does not read as
 * one of those has no place in the sequence, so it can only stand alone.
 */

const SEMESTER_TERMS = ['spring' => 0, 'summer' => 1, 'fall' => 2];

/** [year, term index, term word] for "Summer 2027", or null. */
function semester_parts(string $name): ?array {
    if (!preg_match('/\b(spring|summer|fall)\s+(\d{4})\b/i', $name, $m)) {
        return null;
    }
    return [(int)$m[2], SEMESTER_TERMS[strtolower($m[1])], ucfirst(strtolower($m[1]))];
}

/** A number that goes up by exactly one from each semester to the next. */
function semester_key(string $name): ?int {
    $p = semester_parts($name);
    return $p === null ? null : $p[0] * 3 + $p[1];
}

/**
 * Whether these semester names run back to back with no gap. One semester is
 * always fine; two or more must all read as a term and year, with no
 * duplicates.
 */
function semesters_back_to_back(array $names): bool {
    if (count($names) <= 1) {
        return true;
    }
    $keys = [];
    foreach ($names as $name) {
        $key = semester_key((string)$name);
        if ($key === null) {
            return false;
        }
        $keys[] = $key;
    }
    sort($keys);
    for ($i = 1; $i < count($keys); $i++) {
        if ($keys[$i] !== $keys[$i - 1] + 1) {
            return false;
        }
    }
    return true;
}

/**
 * Semester rows (each with 'name' and, optionally, 'sort_order') in calendar
 * order: by term and year where the name says, otherwise by sort_order, then
 * by name.
 */
function sort_semesters(array $rows): array {
    usort($rows, static function ($a, $b) {
        $ka = semester_key((string)$a['name']);
        $kb = semester_key((string)$b['name']);
        if ($ka !== null && $kb !== null) {
            return $ka <=> $kb;
        }
        return [$ka === null, (int)($a['sort_order'] ?? 0), (string)$a['name']]
            <=> [$kb === null, (int)($b['sort_order'] ?? 0), (string)$b['name']];
    });
    return $rows;
}

/**
 * One label for a run of semesters, in calendar order:
 *
 *   Spring 2027
 *   Summer & Fall 2027
 *   Fall 2027 & Spring 2028
 *   Spring–Fall 2027
 *   Summer 2027–Spring 2028
 *
 * Names that are not a term and year are joined with "&".
 */
function semester_label(array $names): string {
    $names = array_values(array_filter(array_map('strval', $names), 'strlen'));
    if (count($names) <= 1) {
        return $names[0] ?? '';
    }
    $parts = array_map('semester_parts', $names);
    if (in_array(null, $parts, true)) {
        return implode(' & ', $names);
    }
    [$firstYear, , $firstTerm] = $parts[0];
    [$lastYear, , $lastTerm] = $parts[count($parts) - 1];
    $join = count($parts) === 2 ? ' & ' : '–';
    return $firstYear === $lastYear
        ? "$firstTerm$join$lastTerm $lastYear"
        : "$firstTerm $firstYear$join$lastTerm $lastYear";
}

/**
 * Every semester of each listing, keyed by listing id, in calendar order:
 * [['code', 'name', 'open'], …]. `open` is false for a semester that has been
 * deactivated; a code with no `semesters` row counts as open, as it does in
 * the visibility rule. One query for the whole page, like listing_photos().
 */
function listing_semesters(PDO $pdo, array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT ss.sublet_id, ss.semester_code AS code, COALESCE(sem.name, ss.semester_code) AS name,
                (sem.code IS NULL OR sem.active = 1) AS open, COALESCE(sem.sort_order, 0) AS sort_order
           FROM sublet_semesters ss LEFT JOIN semesters sem ON sem.code = ss.semester_code
          WHERE ss.sublet_id IN ($in)"
    );
    $stmt->execute($ids);
    $by = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $by[(int)$row['sublet_id']][] = ['code' => $row['code'], 'name' => $row['name'], 'open' => (bool)$row['open'], 'sort_order' => (int)$row['sort_order']];
    }
    foreach ($by as &$list) {
        $list = array_map(
            static fn($s) => ['code' => $s['code'], 'name' => $s['name'], 'open' => $s['open']],
            sort_semesters($list)
        );
    }
    unset($list);
    return $by;
}

/**
 * Fill in each listing's `semester_name` with the label of its semesters, the
 * open ones only when $openOnly (what students see: a listing still up for
 * Fall need not say Summer once Summer is closed). `semester_codes` gets the
 * same codes, for the pages that compare them. Rows keep their primary
 * `semester` untouched. Returns the listings.
 */
function with_semester_labels(PDO $pdo, array $listings, bool $openOnly): array {
    $all = listing_semesters($pdo, array_column($listings, 'id'));
    foreach ($listings as &$row) {
        $list = $all[(int)$row['id']] ?? [];
        if ($openOnly) {
            $list = array_values(array_filter($list, static fn($s) => $s['open']));
        }
        if (!$list) {
            // No rows at all would be a listing the table missed; it still has
            // its primary semester.
            $list = [['code' => $row['semester'], 'name' => $row['semester_name'] ?? $row['semester'], 'open' => true]];
        }
        $row['semester_codes'] = array_column($list, 'code');
        $row['semester_name'] = semester_label(array_column($list, 'name'));
    }
    unset($row);
    return $listings;
}

/**
 * Replace a listing's semesters, and point `sublets.semester` at the first of
 * them. $codes must already be validated (see post.php). Runs in its own
 * transaction unless one is open.
 */
function set_listing_semesters(PDO $pdo, int $id, array $codes, string $first): void {
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare('DELETE FROM sublet_semesters WHERE sublet_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO sublet_semesters (sublet_id, semester_code) VALUES (?, ?)');
        foreach (array_unique($codes) as $code) {
            $ins->execute([$id, $code]);
        }
        $pdo->prepare('UPDATE sublets SET semester = ? WHERE id = ?')->execute([$first, $id]);
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * The semesters students can filter by: the open semesters of listings on the
 * board, in calendar order, as [['semester' => code, 'name' => name], …].
 */
function board_semesters(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT DISTINCT ss.semester_code AS semester, COALESCE(fsem.name, ss.semester_code) AS name,
                COALESCE(fsem.sort_order, 0) AS sort_order
           FROM sublets s
           JOIN sublet_semesters ss ON ss.sublet_id = s.id
           LEFT JOIN semesters fsem ON fsem.code = ss.semester_code
          WHERE " . PUBLIC_LISTING_WHERE . " AND (fsem.code IS NULL OR fsem.active = 1)"
    )->fetchAll(PDO::FETCH_ASSOC);
    return array_map(
        static fn($r) => ['semester' => $r['semester'], 'name' => $r['name']],
        sort_semesters($rows)
    );
}
