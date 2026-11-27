<?php

namespace App\Repository;

use App\Core\Database;
use App\Core\FormRules;
use App\Core\Mailer;
use PDO;

/** Messages sent from the contact page. */
class ContactRepo
{
    /**
     * Store a message and tell the shop by e-mail when an address is set.
     * @return array ['ok' => true] or ['ok' => false, 'error' => string]
     */
    public static function send(array $data): array
    {
        $errors = FormRules::contact($data);
        if ($errors !== []) {
            return ['ok' => false, 'error' => implode(' ', $errors)];
        }
        $values = [];
        foreach (['name', 'email', 'subject', 'message'] as $field) {
            $values[":{$field}"] = trim($data[$field]);
        }
        Database::getConnection()->prepare(
            'INSERT INTO contact_message (name, email, subject, message) VALUES (:name, :email, :subject, :message)'
        )->execute($values);

        // The message is already saved, so a mail problem is not the sender's problem
        $to = SettingRepo::get('support_email');
        if ($to !== '') {
            // layout() escapes every paragraph itself
            $lines = array_values(array_filter(preg_split('/\R/u', $values[':message']), fn($line) => trim($line) !== ''));
            $body = Mailer::layout(t('app_name'), [
                $values[':name'] . ' <' . $values[':email'] . '>',
                $values[':subject'],
                ...$lines,
            ], t('admin_messages'), url('admin/messages'));
            Mailer::send($to, '', t('contact_mail_subject'), $body);
        }

        return ['ok' => true];
    }

    public static function page(int $page, int $perPage): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM contact_message ORDER BY handled, created_at DESC, message_id DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function count(bool $onlyOpen = false): int
    {
        return (int)Database::getConnection()
            ->query('SELECT COUNT(*) FROM contact_message' . ($onlyOpen ? ' WHERE handled = 0' : ''))->fetchColumn();
    }

    public static function setHandled(int $id, bool $handled): void
    {
        Database::getConnection()->prepare('UPDATE contact_message SET handled = :handled WHERE message_id = :id')
            ->execute([':handled' => (int)$handled, ':id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM contact_message WHERE message_id = :id')->execute([':id' => $id]);
    }
}
