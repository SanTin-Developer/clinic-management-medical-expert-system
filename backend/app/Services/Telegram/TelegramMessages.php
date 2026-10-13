<?php

namespace App\Services\Telegram;

/**
 * Central catalogue of every user-facing RoyalCare Telegram message.
 *
 * All outbound bot messages are defined here so wording stays consistent and
 * safety-reviewed in one place. The Telegram client sends with
 * `parse_mode=HTML`, so any dynamic (user-supplied) value MUST be escaped with
 * `e()` — never pass raw names, titles or message bodies through unescaped.
 * Templates never include passwords, tokens, secrets, raw codes or medical
 * details.
 */
final class TelegramMessages
{
    /**
     * Sent when a user messages the bot with `/start` but no linking code.
     */
    public static function welcome(): string
    {
        return implode("\n", [
            '👋 <b>Welcome to RoyalCare Clinic!</b>',
            '',
            'This bot sends you appointments and clinic notifications on Telegram.',
            '',
            'To connect your account, open the RoyalCare app and go to <b>Settings → Telegram</b>, then tap <b>Link Telegram account</b> and send the code you receive here.',
            '',
            'Type /help to see the available commands.',
        ]);
    }

    /**
     * Shown for `/help` and for any unrecognised command.
     */
    public static function help(): string
    {
        return implode("\n", [
            '🤖 <b>RoyalCare Clinic bot</b>',
            '',
            '<b>Commands</b>',
            '/start — connect your account (use the link from the app)',
            '/stop — disconnect your account',
            '/chats — list your conversations',
            '/open &lt;id&gt; — choose a conversation to message',
            '/close — stop messaging the current conversation',
            '/help — show this message',
            '',
            '<b>Messaging</b>',
            'After /open, just type to send a message to that conversation. You can also use Telegram\'s <b>Reply</b> action on a RoyalCare notification.',
            '',
            'To connect, open the RoyalCare app and go to <b>Settings → Telegram</b>, then tap <b>Link Telegram account</b>.',
        ]);
    }

    /**
     * The linking code did not match any active code.
     */
    public static function invalidCode(): string
    {
        return implode("\n", [
            '❌ That linking code is invalid.',
            '',
            'Please copy the whole code exactly as shown, then try again. You can generate a fresh code in the RoyalCare app under <b>Settings → Telegram</b>.',
        ]);
    }

    /**
     * The linking code existed but has passed its time-to-live.
     */
    public static function expiredCode(): string
    {
        return implode("\n", [
            '⌛ That linking code has expired.',
            '',
            'For your security, linking codes are short-lived. Open the RoyalCare app and go to <b>Settings → Telegram</b>, then tap <b>Link Telegram account</b> to get a new code.',
        ]);
    }

    /**
     * The linking code was already consumed.
     */
    public static function usedCode(): string
    {
        return implode("\n", [
            '✅ This linking code has already been used.',
            '',
            'If you are still not connected, open the RoyalCare app and go to <b>Settings → Telegram</b> to generate a new code.',
        ]);
    }

    /**
     * The Telegram chat is already bound to a different RoyalCare user.
     */
    public static function conflictingAccount(): string
    {
        return implode("\n", [
            '🔗 This Telegram account is already linked to another RoyalCare user.',
            '',
            'If this is your Telegram account, send /stop here to unlink it first, then generate a new code in the RoyalCare app under <b>Settings → Telegram</b>.',
        ]);
    }

    /**
     * Account linked successfully.
     */
    public static function linkingSuccess(?string $telegramUsername = null): string
    {
        $greeting = $telegramUsername !== null && $telegramUsername !== ''
            ? '👋 <b>Welcome, @'.e($telegramUsername).'!</b>'
            : '👋 <b>Welcome to RoyalCare Clinic!</b>';

        return implode("\n", [
            $greeting,
            '',
            'You are now connected — we will send your staff notifications and clinic updates here.',
            '',
            'Send /stop at any time if you would like to disconnect.',
        ]);
    }

    /**
     * The chat was unlinked via `/stop`.
     */
    public static function unlinked(): string
    {
        return implode("\n", [
            '👍 You have been unlinked from RoyalCare.',
            '',
            'You will no longer receive notifications in this chat. To reconnect, open the RoyalCare app and go to <b>Settings → Telegram</b>.',
        ]);
    }

    /**
     * `/stop` was sent from a chat that is not linked.
     */
    public static function alreadyUnlinked(): string
    {
        return implode("\n", [
            'ℹ️ This chat is not linked to a RoyalCare account.',
            '',
            'To connect, open the RoyalCare app and go to <b>Settings → Telegram</b>, then tap <b>Link Telegram account</b>.',
        ]);
    }

    /**
     * New staff message notification (direct or group).
     */
    public static function newMessage(string $senderName, ?string $conversationTitle, string $body): string
    {
        $context = $conversationTitle !== null && $conversationTitle !== ''
            ? ' in <b>'.e($conversationTitle).'</b>'
            : '';

        return '💬 <b>'.e($senderName).'</b>'.$context."\n".e($body);
    }

    /**
     * Confirmation that an inbound Telegram reply was saved to the conversation.
     */
    public static function replyRecorded(?string $conversationTitle = null): string
    {
        $context = $conversationTitle !== null && $conversationTitle !== ''
            ? ' to <b>'.e($conversationTitle).'</b>'
            : '';

        return '✅ Your reply was sent'.$context.'. It will appear in the RoyalCare app.';
    }

    /**
     * Confirmation that a free-text message was sent to the selected conversation.
     */
    public static function messageSent(?string $conversationTitle = null): string
    {
        $context = $conversationTitle !== null && $conversationTitle !== ''
            ? ' to <b>'.e($conversationTitle).'</b>'
            : '';

        return '✅ Your message was sent'.$context.'. It will appear in the RoyalCare app.';
    }

    /**
     * The list produced by `/chats`.
     *
     * @param  array<int, array{id: int, title: string, type: string}>  $conversations
     */
    public static function conversationList(array $conversations, ?int $activeId = null): string
    {
        if ($conversations === []) {
            return self::noConversations();
        }

        $lines = ['💬 <b>Your conversations</b>', ''];

        foreach ($conversations as $conversation) {
            $marker = $conversation['id'] === $activeId ? ' (current)' : '';

            $lines[] = '#'.$conversation['id'].' — <b>'.e($conversation['title']).'</b>'.$marker;
        }

        $lines[] = '';
        $lines[] = 'Use /open &lt;id&gt; to choose one, then type to send a message.';

        return implode("\n", $lines);
    }

    /**
     * The linked user has no active conversations to message.
     */
    public static function noConversations(): string
    {
        return implode("\n", [
            '💬 You have no conversations to message yet.',
            '',
            'Start a conversation in the RoyalCare app first, then try /chats again.',
        ]);
    }

    /**
     * A conversation was successfully selected via `/open`.
     */
    public static function conversationOpened(string $title): string
    {
        return implode("\n", [
            '📨 You are now messaging <b>'.e($title).'</b>.',
            '',
            'Type your message here to send it to this conversation. Use /close to stop, or /chats to switch.',
        ]);
    }

    /**
     * `/open` referenced a conversation the user cannot access (or that does not exist).
     */
    public static function conversationNotFound(): string
    {
        return implode("\n", [
            '❌ I could not find that conversation.',
            '',
            'Use /chats to list the conversations you can message.',
        ]);
    }

    /**
     * `/open` was used incorrectly.
     */
    public static function openUsage(): string
    {
        return implode("\n", [
            'ℹ️ Usage: /open &lt;id&gt;',
            '',
            'Use /chats to list your conversations and copy the number shown next to the one you want.',
        ]);
    }

    /**
     * `/open` was attempted by a user who cannot send messages.
     */
    public static function conversationForbidden(): string
    {
        return '⛔ You do not have permission to send messages in that conversation.';
    }

    /**
     * The active conversation was cleared via `/close`.
     */
    public static function conversationClosed(): string
    {
        return implode("\n", [
            '👍 You are no longer messaging a specific conversation.',
            '',
            'Use /chats and /open &lt;id&gt; to choose another, or reply to a RoyalCare notification.',
        ]);
    }

    /**
     * Plain text that could not be routed to any conversation.
     */
    public static function noActiveConversation(): string
    {
        return implode("\n", [
            'ℹ️ I could not tell which conversation this message is for.',
            '',
            'Use /chats to list your conversations, then /open &lt;id&gt; to choose one and type your message. You can also use Telegram\'s <b>Reply</b> action on a RoyalCare notification.',
        ]);
    }

    /**
     * The user sent a plain message that is not a reply to a RoyalCare notification.
     */
    public static function replyNotRecognized(): string
    {
        return implode("\n", [
            'ℹ️ I could not match your message to a RoyalCare notification.',
            '',
            'To reply, use Telegram\'s <b>Reply</b> action on the RoyalCare message you received, then type your response. Your reply will be saved to the related conversation in the RoyalCare app.',
            '',
            'Type /help to see the available commands.',
        ]);
    }

    /**
     * A reply was received from a Telegram chat that is not linked.
     */
    public static function replyNotLinked(): string
    {
        return implode("\n", [
            '🔒 This Telegram chat is not linked to a RoyalCare account.',
            '',
            'Open the RoyalCare app and go to <b>Settings → Telegram</b>, then tap <b>Link Telegram account</b> to connect.',
        ]);
    }

    /**
     * The sender is not an active participant of the referenced conversation.
     */
    public static function replyUnauthorized(): string
    {
        return '⛔ You are not a participant in that RoyalCare conversation, so your reply was not saved.';
    }

    /**
     * The reply was empty after trimming.
     */
    public static function replyEmpty(): string
    {
        return '⚠️ Your reply was empty, so nothing was saved. Please type a message and try again.';
    }

    /**
     * The reply exceeded the configured maximum length.
     */
    public static function replyTooLong(int $limit): string
    {
        return '⚠️ Your reply is too long (maximum '.$limit.' characters). Please shorten it and try again. It was not saved.';
    }
}
