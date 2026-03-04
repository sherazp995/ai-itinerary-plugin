import { create } from 'zustand';

const config = window.aipConfig || {};

export const useAppStore = create((set, get) => ({
  // UI state
  isOpen: false,
  setIsOpen: (isOpen) => set({ isOpen }),

  // View: 'chat' | 'itinerary' | 'auth'
  view: 'chat',
  setView: (view) => set({ view }),

  // User state
  user: null,
  setUser: (user) => set({ user }),
  isLoggedIn: () => get().user?.logged_in === true,
  hasPremium: () => get().user?.has_premium === true,

  // Chat state
  messages: [],
  addMessage: (msg) => set((s) => ({ messages: [...s.messages, msg] })),
  setMessages: (messages) => set({ messages }),
  clearMessages: () => set({ messages: [] }),

  // Collected trip data
  collectedData: {},
  setCollectedData: (data) => set({ collectedData: data }),

  // Ready to generate
  ready: false,
  setReady: (ready) => set({ ready }),
  missing: [],
  setMissing: (missing) => set({ missing }),

  // Itinerary result
  itinerary: null,
  setItinerary: (itinerary) => set({ itinerary, view: 'itinerary' }),
  affiliateLinks: [],
  setAffiliateLinks: (links) => set({ affiliateLinks: links }),

  // Loading states
  isSending: false,
  setIsSending: (v) => set({ isSending: v }),
  isGenerating: false,
  setIsGenerating: (v) => set({ isGenerating: v }),

  // Auth modal
  showAuth: false,
  setShowAuth: (v) => set({ showAuth: v }),

  // Config
  config: {
    botName: config.bot_name || 'Travel Buddy',
    primaryColor: config.primary_color || '#2271b1',
    secondaryColor: config.secondary_color || '#135e96',
    freeLimit: config.free_limit || 3,
    googleClientId: config.google_client_id || '',
    defaultLanguage: config.default_language || 'en',
  },

  // Reset conversation
  resetChat: () => set({
    messages: [],
    collectedData: {},
    ready: false,
    missing: [],
    itinerary: null,
    affiliateLinks: [],
    view: 'chat',
  }),
}));
