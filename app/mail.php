<?php
declare(strict_types=1);

/** Versendet eine Text-Mail über PHP mail() (bei IONOS ohne weitere Konfiguration nutzbar). */
function send_mail(string $to, string $subject, string $text): bool
{
    if (!config('mail_enabled', true) || !is_email($to)) {
        return false;
    }
    $from = config('mail_from', 'no-reply@sellersdelight.de');
    $fromName = config('mail_from_name', "Seller's Delight");
    $headers = [
        'From' => sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($fromName), $from),
        'Reply-To' => config('contact_email') ?: $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers, '-f' . $from);
    if (!$ok) {
        error_log("[mail] Versand an $to fehlgeschlagen: $subject");
    }
    return $ok;
}

/** Benachrichtigt den Betreiber über neue Einreichungen (optional, config admin_notify_email). */
function notify_admin(string $subject, string $text): void
{
    if ($to = config('admin_notify_email')) {
        send_mail($to, $subject, $text);
    }
}

const INVITE_DAYS = 14;

/** Erzeugt einen Einladungslink und versendet ihn. Gibt [link, sent] zurück. */
function send_invite(array $user, ?string $providerName = null): array
{
    $t = token();
    Users::setInvite((int) $user['id'], $t, time() + INVITE_DAYS * 86400);
    $link = base_url() . '/einladung/' . $t;
    $intro = $providerName
        ? "für Ihr Unternehmen „{$providerName}“ wurde auf Seller's Delight ein Zugang angelegt. Damit können Sie Ihr Profil – z. B. Firmendaten, Beschreibung und Produkte – selbst pflegen."
        : "für Sie wurde ein Zugang zu Seller's Delight angelegt.";
    $sent = send_mail(
        $user['email'],
        $providerName ? "Ihr Profil „{$providerName}“ auf Seller's Delight" : "Ihr Zugang zu Seller's Delight",
        "Hallo {$user['name']},\n\n{$intro}\n\nÜber folgenden Link legen Sie Ihr Passwort fest (gültig " . INVITE_DAYS . " Tage):\n{$link}\n\nViele Grüße\nIhr Team von Seller's Delight"
    );
    return [$link, $sent];
}

function invite_flash(array $result, string $email): void
{
    [$link, $sent] = $result;
    if ($sent) {
        flash('success', "Einladung an $email versendet. Falls sie nicht ankommt, diesen Link weitergeben: $link");
    } else {
        flash('info', "E-Mail konnte nicht versendet werden. Bitte diesen Einladungslink an $email weitergeben: $link");
    }
}
