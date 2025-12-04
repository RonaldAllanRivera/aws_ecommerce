/***** Tailwind CSS config for the SPA *****/
import defaultTheme from 'tailwindcss/defaultTheme'

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['system-ui', ...defaultTheme.fontFamily.sans],
      },
    },
  },
  plugins: [],
}
