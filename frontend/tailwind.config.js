/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Override indigo with sensea brand colors
        indigo: {
          50: '#faf5ff',
          100: '#f3e8ff',
          200: '#e9d5ff',
          300: '#d8b4fe',
          400: '#a855f7',
          500: '#9333ea',   // sensea-light
          600: '#721ad6',   // sensea (main CTA)
          700: '#5b14a8',   // sensea-dark
          800: '#4c1389',
          900: '#3b0f6a',
        },
        sensea: {
          light: '#9333ea',
          DEFAULT: '#721ad6',
          dark: '#5b14a8'
        }
      },
      fontFamily: {
        sans: ['Satoshi', 'system-ui', 'sans-serif'],
        logo: ['Amandine', 'system-ui', 'sans-serif'],
        heading: ['Syne', 'system-ui', 'sans-serif'],
      },
      backgroundImage: {
        'sensea-gradient': 'linear-gradient(82.48deg, transparent -1.1%, rgba(114, 26, 214, 0.36) 96.45%)',
        'sensea-cta': 'linear-gradient(to right, #9333ea, #721ad6)',
      }
    },
  },
  plugins: [],
}
