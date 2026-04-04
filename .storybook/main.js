import vue from '@vitejs/plugin-vue';

/** @type { import('@storybook/vue3-vite').StorybookConfig } */
const config = {
  stories: ['../assets/vue/**/*.stories.@(js|ts)'],
  addons: ['@storybook/addon-essentials'],
  framework: {
    name: '@storybook/vue3-vite',
    options: {},
  },
  async viteFinal(config) {
    config.plugins = config.plugins || [];
    config.plugins.push(vue());
    return config;
  },
};

export default config;
