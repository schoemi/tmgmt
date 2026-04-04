import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import { setup } from '@storybook/vue3';

setup((app) => {
  app.use(createPinia());
  app.use(PrimeVue, {
    theme: {
      preset: Aura
    }
  });
});

/** @type { import('@storybook/vue3').Preview } */
const preview = {
  parameters: {
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
  },
};

export default preview;
