<?php

namespace Tests\Unit;

use App\Services\Telegram\TelegramMessages;
use PHPUnit\Framework\TestCase;

class TelegramMessagesTest extends TestCase
{
    public function test_welcome_guides_the_user_to_the_app(): void
    {
        $message = TelegramMessages::welcome();

        $this->assertStringContainsString('Welcome to RoyalCare', $message);
        $this->assertStringContainsString('Settings → Telegram', $message);
        $this->assertStringContainsString('Link Telegram account', $message);
    }

    public function test_help_lists_supported_commands(): void
    {
        $message = TelegramMessages::help();

        $this->assertStringContainsString('/start', $message);
        $this->assertStringContainsString('/stop', $message);
        $this->assertStringContainsString('/help', $message);
        $this->assertStringContainsString('/chats', $message);
        $this->assertStringContainsString('/open', $message);
        $this->assertStringContainsString('/close', $message);
    }

    public function test_failure_messages_describe_the_actual_result(): void
    {
        $this->assertStringContainsString('invalid', TelegramMessages::invalidCode());
        $this->assertStringContainsString('expired', TelegramMessages::expiredCode());
        $this->assertStringContainsString('already been used', TelegramMessages::usedCode());
        $this->assertStringContainsString('already linked', TelegramMessages::conflictingAccount());
    }

    public function test_linking_success_confirms_connection_and_escapes_username(): void
    {
        $message = TelegramMessages::linkingSuccess('nurse_jane');

        $this->assertStringContainsString('connected', $message);
        $this->assertStringContainsString('@nurse_jane', $message);
        $this->assertStringContainsString('/stop', $message);

        $unsafe = TelegramMessages::linkingSuccess('bad<script>name');
        $this->assertStringNotContainsString('<script>', $unsafe);
        $this->assertStringContainsString('bad&lt;script&gt;name', $unsafe);
    }

    public function test_unlink_messages_are_distinct(): void
    {
        $this->assertStringContainsString('unlinked', TelegramMessages::unlinked());
        $this->assertStringContainsString('not linked', TelegramMessages::alreadyUnlinked());
        $this->assertNotSame(TelegramMessages::unlinked(), TelegramMessages::alreadyUnlinked());
    }

    public function test_new_message_escapes_user_supplied_content(): void
    {
        $message = TelegramMessages::newMessage('Jane <b>Doe</b>', 'Ward <i>1</i>', 'Body <script>alert(1)</script>');

        $this->assertStringContainsString('💬', $message);
        $this->assertStringContainsString('Jane &lt;b&gt;Doe&lt;/b&gt;', $message);
        $this->assertStringContainsString('Ward &lt;i&gt;1&lt;/i&gt;', $message);
        $this->assertStringContainsString('Body &lt;script&gt;alert(1)&lt;/script&gt;', $message);
        $this->assertStringNotContainsString('<script>', $message);
    }

    public function test_new_message_omits_context_for_direct_messages(): void
    {
        $message = TelegramMessages::newMessage('Jane', null, 'Hello');

        $this->assertStringNotContainsString(' in ', $message);
        $this->assertStringContainsString('Hello', $message);
    }

    public function test_reply_recorded_confirms_and_escapes_the_conversation_title(): void
    {
        $this->assertStringContainsString('reply was sent', TelegramMessages::replyRecorded('Ward'));

        $unsafe = TelegramMessages::replyRecorded('<script>bad</script>');

        $this->assertStringNotContainsString('<script>', $unsafe);
        $this->assertStringContainsString('&lt;script&gt;bad&lt;/script&gt;', $unsafe);
    }

    public function test_reply_recorded_omits_context_for_direct_messages(): void
    {
        $message = TelegramMessages::replyRecorded(null);

        $this->assertStringContainsString('reply was sent', $message);
        $this->assertStringNotContainsString(' to ', $message);
    }

    public function test_reply_failure_messages_describe_the_actual_result(): void
    {
        $this->assertStringContainsString('could not match', TelegramMessages::replyNotRecognized());
        $this->assertStringContainsString('not linked', TelegramMessages::replyNotLinked());
        $this->assertStringContainsString('not a participant', TelegramMessages::replyUnauthorized());
        $this->assertStringContainsString('empty', TelegramMessages::replyEmpty());
        $this->assertStringContainsString('too long', TelegramMessages::replyTooLong(4000));
        $this->assertStringContainsString('4000', TelegramMessages::replyTooLong(4000));
    }

    public function test_message_sent_confirms_and_escapes_the_conversation_title(): void
    {
        $this->assertStringContainsString('message was sent', TelegramMessages::messageSent('Ward'));

        $unsafe = TelegramMessages::messageSent('<script>bad</script>');

        $this->assertStringNotContainsString('<script>', $unsafe);
        $this->assertStringContainsString('&lt;script&gt;bad&lt;/script&gt;', $unsafe);
    }

    public function test_conversation_list_formats_and_escapes_each_entry(): void
    {
        $message = TelegramMessages::conversationList([
            ['id' => 12, 'title' => 'Ward <b>1</b>', 'type' => 'group'],
            ['id' => 34, 'title' => 'Dr. Jane', 'type' => 'private'],
        ], 34);

        $this->assertStringContainsString('#12', $message);
        $this->assertStringContainsString('Ward &lt;b&gt;1&lt;/b&gt;', $message);
        $this->assertStringContainsString('#34', $message);
        $this->assertStringContainsString('Dr. Jane', $message);
        $this->assertStringContainsString('(current)', $message);
        $this->assertStringNotContainsString('<b>1</b>', $message);
    }

    public function test_conversation_list_falls_back_to_empty_message(): void
    {
        $this->assertSame(TelegramMessages::noConversations(), TelegramMessages::conversationList([]));
        $this->assertStringContainsString('no conversations', TelegramMessages::noConversations());
    }

    public function test_conversation_opened_escapes_the_title(): void
    {
        $message = TelegramMessages::conversationOpened('Ward <b>1</b>');

        $this->assertStringContainsString('now messaging', $message);
        $this->assertStringContainsString('Ward &lt;b&gt;1&lt;/b&gt;', $message);
        $this->assertStringNotContainsString('<b>1</b>', $message);
    }

    public function test_open_usage_and_not_found_messages_are_helpful(): void
    {
        $this->assertStringContainsString('/open', TelegramMessages::openUsage());
        $this->assertStringContainsString('could not find', TelegramMessages::conversationNotFound());
        $this->assertStringContainsString('no longer messaging', TelegramMessages::conversationClosed());
        $this->assertStringContainsString('could not tell', TelegramMessages::noActiveConversation());
    }
}
