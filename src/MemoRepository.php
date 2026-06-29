<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

/**
 * All database access for memos, with per-user ownership enforced in SQL.
 *
 * Every read and write is scoped by user_id so a user can never touch another
 * user's rows, regardless of what id the request supplies. This is the server-side
 * ownership guarantee; the UI is never trusted for it.
 *
 * The repository owns persistence only. Repeat math lives in {@see Reminder};
 * the HTTP handler in index.php wires the two together.
 */
final readonly class MemoRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM memos WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);

        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $memo = [];
            foreach ($row as $key => $value) {
                $memo[(string) $key] = $value;
            }
            $result[] = $memo;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM memos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        if (! is_array($row)) {
            return null;
        }

        /** @var array<string, mixed> $result */
        $result = [];
        foreach ($row as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    public function create(
        int $userId,
        string $title,
        string $content,
        string $remindAt,
        string $repeatMode,
        string $repeatConfig
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO memos (user_id, title, content, remind_at, repeat_mode, repeat_config, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $title,
            $content,
            $remindAt,
            $repeatMode,
            $repeatConfig,
            (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);
    }

    public function update(
        int $id,
        int $userId,
        string $title,
        string $content,
        string $remindAt,
        string $repeatMode,
        string $repeatConfig
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE memos
             SET title = ?, content = ?, remind_at = ?, repeat_mode = ?, repeat_config = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$title, $content, $remindAt, $repeatMode, $repeatConfig, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM memos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    /**
     * Persist a recomputed reminder time for a repeating memo.
     */
    public function updateRemindAt(int $id, int $userId, string $remindAt): void
    {
        $stmt = $this->db->prepare('UPDATE memos SET remind_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$remindAt, $id, $userId]);
    }
}
