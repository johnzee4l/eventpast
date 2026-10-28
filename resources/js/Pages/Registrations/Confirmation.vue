<template>
    <PublicLayout>
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <!-- Success Icon -->
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    {{ registration.status === 'confirmed' ? 'Registration Confirmed!' : 'Added to Waitlist' }}
                </h1>
                
                <p class="text-lg text-gray-600 mb-8">
                    {{ registration.status === 'confirmed' 
                        ? 'You\'re all set! Check your email for confirmation details.' 
                        : 'We\'ll notify you if a spot opens up.' 
                    }}
                </p>
                
                <!-- Event Details -->
                <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Event Details</h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Event</dt>
                            <dd class="text-base text-gray-900">{{ registration.event.title }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Date & Time</dt>
                            <dd class="text-base text-gray-900">{{ formatDate(registration.event.start_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Location</dt>
                            <dd class="text-base text-gray-900">{{ registration.event.venue_name || 'Online' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ticket Type</dt>
                            <dd class="text-base text-gray-900">{{ registration.ticket_type.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Attendee</dt>
                            <dd class="text-base text-gray-900">{{ registration.attendee.name }} ({{ registration.attendee.email }})</dd>
                        </div>
                    </dl>
                </div>
                
                <!-- QR Code (only for confirmed registrations) -->
                <div v-if="registration.status === 'confirmed'" class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Your Ticket</h2>
                    <div class="bg-white border-2 border-gray-200 rounded-lg p-6 inline-block">
                        <img 
                            :src="qrcodeUrl" 
                            alt="QR Code" 
                            class="w-64 h-64 mx-auto"
                        />
                        <p class="text-sm text-gray-600 text-center mt-4">Show this QR code at check-in</p>
                    </div>
                    <p class="text-xs text-gray-500 mt-4">This QR code has been sent to your email</p>
                </div>
                
                <!-- Referral Link -->
                <div v-if="registration.referral" class="bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-lg p-6 mb-8">
                    <div class="text-center mb-4">
                        <h3 class="text-xl font-bold text-gray-900 mb-2">🎉 Invite Your Friends</h3>
                        <p class="text-sm text-gray-700">Share this event and earn rewards when friends register!</p>
                        <div class="mt-2 inline-flex items-center space-x-2 text-sm">
                            <span class="font-semibold text-purple-700">{{ registration.referral.conversions }}</span>
                            <span class="text-gray-600">friends joined</span>
                            <span class="text-gray-400">•</span>
                            <span class="font-semibold text-indigo-700">{{ registration.referral.clicks }}</span>
                            <span class="text-gray-600">clicks</span>
                        </div>
                    </div>
                    
                    <!-- Copy Link -->
                    <div class="flex items-center space-x-2 mb-4">
                        <input 
                            :value="referralUrl" 
                            readonly
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm bg-white font-mono text-xs"
                        />
                        <button 
                            @click="copyReferralLink"
                            class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700 transition"
                        >
                            {{ copied ? '✓ Copied!' : 'Copy Link' }}
                        </button>
                    </div>
                    
                    <!-- Social Share Buttons -->
                    <div class="flex justify-center space-x-3">
                        <a 
                            :href="shareOnTwitter" 
                            target="_blank"
                            class="flex items-center space-x-2 px-4 py-2 bg-sky-500 text-white rounded-md hover:bg-sky-600 transition text-sm font-medium"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/>
                            </svg>
                            <span>Twitter</span>
                        </a>
                        <a 
                            :href="shareOnFacebook" 
                            target="_blank"
                            class="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-sm font-medium"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            <span>Facebook</span>
                        </a>
                        <a 
                            :href="shareOnWhatsApp" 
                            target="_blank"
                            class="flex items-center space-x-2 px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition text-sm font-medium"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            <span>WhatsApp</span>
                        </a>
                        <button 
                            @click="shareViaEmail"
                            class="flex items-center space-x-2 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition text-sm font-medium"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>Email</span>
                        </button>
                    </div>
                    
                    <!-- Reward Info -->
                    <div class="mt-4 pt-4 border-t border-purple-200 text-center">
                        <p class="text-xs text-gray-600">
                            💡 <span class="font-semibold">Tip:</span> Invite 3 friends and unlock special rewards!
                        </p>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="space-y-3">
                    <a 
                        :href="calendarUrl" 
                        class="block w-full px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-md hover:bg-gray-200"
                    >
                        Add to Calendar
                    </a>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    registration: Object,
});

const copied = ref(false);

const qrcodeUrl = computed(() => {
    return `/registrations/${props.registration.id}/qrcode`;
});

const calendarUrl = computed(() => {
    return route('public.events.calendar', props.registration.event.slug);
});

const referralUrl = computed(() => {
    if (!props.registration.referral) return '';
    return `${window.location.origin}/events/${props.registration.event.slug}?ref=${props.registration.referral.code}`;
});

const copyReferralLink = () => {
    navigator.clipboard.writeText(referralUrl.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const shareOnTwitter = computed(() => {
    const text = `I just registered for ${props.registration.event.title}! Join me at this event:`;
    return `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(referralUrl.value)}`;
});

const shareOnFacebook = computed(() => {
    return `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralUrl.value)}`;
});

const shareOnWhatsApp = computed(() => {
    const text = `I just registered for ${props.registration.event.title}! Join me: ${referralUrl.value}`;
    return `https://wa.me/?text=${encodeURIComponent(text)}`;
});

const shareViaEmail = () => {
    const subject = `Join me at ${props.registration.event.title}`;
    const body = `Hi!\n\nI just registered for ${props.registration.event.title} and thought you might be interested too!\n\nEvent Details:\n${formatDate(props.registration.event.start_at)}\n${props.registration.event.venue_name || 'Online'}\n\nRegister here: ${referralUrl.value}\n\nHope to see you there!`;
    window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>
