(function (global) {
    'use strict';

    var audioCtx = null;

    function getAudioContext() {
        if (audioCtx) {
            return audioCtx;
        }
        var Ctx = global.AudioContext || global.webkitAudioContext;
        if (!Ctx) {
            return null;
        }
        audioCtx = new Ctx();
        return audioCtx;
    }

    function playTone(frequency, durationSec, type) {
        var ctx = getAudioContext();
        if (!ctx) {
            return;
        }
        if (ctx.state === 'suspended') {
            ctx.resume().catch(function () {});
        }

        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        var now = ctx.currentTime;
        var duration = Math.max(0.04, durationSec || 0.08);

        osc.type = type || 'sine';
        osc.frequency.setValueAtTime(frequency, now);
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.18, now + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + duration + 0.02);
    }

    function vibrate(pattern) {
        if (!global.navigator || typeof global.navigator.vibrate !== 'function') {
            return;
        }
        try {
            global.navigator.vibrate(pattern);
        } catch (e) {
            // Sin permiso o navegador sin soporte real.
        }
    }

    global.JoyeriaPosScanFeedback = {
        prepare: function () {
            var ctx = getAudioContext();
            if (ctx && ctx.state === 'suspended') {
                ctx.resume().catch(function () {});
            }
        },
        success: function () {
            vibrate(40);
            playTone(1046, 0.07, 'sine');
        },
        error: function () {
            vibrate([70, 45, 70]);
            playTone(220, 0.13, 'square');
        }
    };
})(window);
