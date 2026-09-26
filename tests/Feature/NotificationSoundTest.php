<?php

namespace Tests\Feature;

use App\Models\NotificationSound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationSoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_sounds_page(): void
    {
        $this->get(route('sounds.index'))->assertRedirect(route('login'));
    }

    public function test_an_uploaded_sound_is_stored_and_becomes_the_active_sound(): void
    {
        Storage::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('sounds.store'), ['name' => 'Church bell', 'sound' => UploadedFile::fake()->create('bell.mp3', 100, 'audio/mpeg')])
            ->assertRedirect(route('sounds.index'));

        $sound = NotificationSound::firstOrFail();
        $this->assertTrue($sound->is_active);
        Storage::assertExists($sound->path);
        $this->assertSame(route('sounds.file', $sound), NotificationSound::activeUrl());
    }

    public function test_non_audio_and_oversized_files_are_rejected(): void
    {
        Storage::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('sounds.store'), ['name' => 'x', 'sound' => UploadedFile::fake()->create('a.php', 10)])->assertSessionHasErrors('sound');
        $this->actingAs($user)->post(route('sounds.store'), ['name' => 'x', 'sound' => UploadedFile::fake()->create('a.mp3', 5000, 'audio/mpeg')])->assertSessionHasErrors('sound');
        $this->assertDatabaseCount('notification_sounds', 0);
    }

    public function test_only_one_sound_is_active_and_the_default_can_be_restored(): void
    {
        $first = NotificationSound::factory()->active()->create();
        $second = NotificationSound::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->patch(route('sounds.activate', $second));
        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);

        $this->post(route('sounds.default'));
        $this->assertNull(NotificationSound::activeUrl());
    }

    public function test_removing_a_sound_deletes_its_file(): void
    {
        Storage::fake();
        Storage::put('sounds/a.mp3', 'x');
        $sound = NotificationSound::factory()->create(['path' => 'sounds/a.mp3']);

        $this->actingAs(User::factory()->create())->delete(route('sounds.destroy', $sound))->assertRedirect(route('sounds.index'));

        Storage::assertMissing('sounds/a.mp3');
        $this->assertDatabaseCount('notification_sounds', 0);
    }
}
