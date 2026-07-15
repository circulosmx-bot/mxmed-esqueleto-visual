/** @type {import('jest').Config} */
module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'node',
  roots: ['<rootDir>/test'],
  testMatch: ['**/*.test.ts'],
  setupFilesAfterEnv: ['aws-cdk-lib/testhelpers/jest-autoclean'],
  clearMocks: true,
  collectCoverageFrom: ['bin/**/*.ts', 'lib/**/*.ts'],
  coverageDirectory: 'coverage',
};
