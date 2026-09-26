/**
 * Plays the notification sound: an uploaded audio file when a URL is given, otherwise a built-in melody.
 * Browsers only allow audio after a user gesture, so callers unlock it through a click first.
 */
(() => {
    let audioContext = null;

    function unlockAudio() {
        audioContext ??= new (window.AudioContext || window.webkitAudioContext)();
        if (audioContext.state === 'suspended') audioContext.resume();
    }

    function playBuiltInMelody() {
        unlockAudio();
        const notes = [[659.25, 0], [783.99, .18], [987.77, .36], [1318.51, .54], [987.77, .82], [1318.51, 1]];
        notes.forEach(([frequency, offset]) => {
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            const start = audioContext.currentTime + offset;
            oscillator.type = 'triangle';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.35, start + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.5);
            oscillator.connect(gain).connect(audioContext.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.55);
        });
    }

    window.unlockNotificationAudio = unlockAudio;
    window.playNotificationSound = (url) => {
        try {
            if (url) {
                const audio = new Audio(url);
                audio.play().catch((error) => { console.warn('Uploaded sound blocked, using built-in melody', error); playBuiltInMelody(); });
                return;
            }
            playBuiltInMelody();
        } catch (error) { console.warn('Unable to play sound', error); }
    };
})();
