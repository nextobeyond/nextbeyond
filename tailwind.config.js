/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.{php,html}",
    "./includes/**/*.{php,html}",
    "./admin/**/*.{php,html}",
    "./admin/ai-exam-app/**/*.{php,html,js}",
    "./student/**/*.{php,html}",
    "./assets/js/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        navy: {
          800: '#11386e',
          900: '#09234d',
          950: '#061633',
        },
        primary: {
          100: '#e8f1ff',
          500: '#3981f5',
          600: '#2369dd',
        },
        pink: {
          100: '#ffe7f2',
          500: '#f54696',
          600: '#e72d82',
        },
        violet: {
          100: '#f2eaff',
        },
        ink: '#12213b',
        muted: '#65738a',
        line: '#dce4ef',
        surface: {
          DEFAULT: '#ffffff',
          soft: '#f6f8fc',
        },
        success: '#168765',
        warning: '#bf6b12',
        danger: '#c9364c',
      },
      fontFamily: {
        sans: [
          '"IBM Plex Sans Thai"',
          '"IBM Plex Sans"',
          'Tahoma',
          'system-ui',
          '-apple-system',
          'sans-serif'
        ],
      },
      borderRadius: {
        'md': '14px',
        'lg': '20px',
        'xl': '28px',
      },
      boxShadow: {
        'soft': '0 12px 32px rgba(15, 42, 83, 0.08)',
        'default': '0 20px 60px rgba(15, 42, 83, 0.12)',
        'pink': '0 12px 25px rgba(231,45,130,.25)',
      }
    },
  },
  plugins: [],
}
