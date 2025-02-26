
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class', // Enable dark mode using a class
  content: [],
  theme: {
    extend: {},
  },
  plugins: [],
}

module.exports = {
  content: ["./src/**/*.{js,jsx}"],
  theme: {
    extend: {
      backgroundImage: {
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
      }
    }
  }
}
/pre>

@keyframes gradient-shift {
  0% {
    background-position: 0% 50%;
}

  50% {
    background-position: 100% 50%;
}

  100% {
    background-position: 0% 50%;
}
}

.animate-gradient {
  animation: gradient-shift 15s ease infinite;
}

module.exports = {
  theme: {
    extend: {
      keyframes: {
        'gradient-shift': {
          '0%, 100%': { backgroundPosition: '0% 50%' },
          '50%': { backgroundPosition: '100% 50%' },
        }
      },
      animation: {
        'gradient': 'gradient-shift 15s ease infinite',
      }
    },
  },
  plugins: [],
}
