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
 *
 * @phpstan-type MemoRow array{
 *     id: int,
 *     user_id: int,
 *     title: string,
 *     content: string,
 *     remind_at: string,
 *     repeat_mode: string,
 *     repeat_config: string,
 *     created_at: string
 * }
 */
final readonly class MemoRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<MemoRow>
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

            $result[] = $this->hydrateMemo($row);
        }

        return $result;
    }

    /**
     * @return MemoRow|null
     */
    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM memos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        if (! is_array($row)) {
            return null;
        }

        return $this->hydrateMemo($row);
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

    /**
     * @param  array<string|int, mixed>  $row
     * @return MemoRow
     */
    private function hydrateMemo(array $row): array
    {
        return [
            'id' => $this->intValue($row['id'] ?? 0),
            'user_id' => $this->intValue($row['user_id'] ?? 0),
            'title' => $this->stringValue($row['title'] ?? ''),
            'content' => $this->stringValue($row['content'] ?? ''),
            'remind_at' => $this->stringValue($row['remind_at'] ?? ''),
            'repeat_mode' => $this->stringValue($row['repeat_mode'] ?? 'none'),
            'repeat_config' => $this->stringValue($row['repeat_config'] ?? ''),
            'created_at' => $this->stringValue($row['created_at'] ?? ''),
        ];
    }

    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
