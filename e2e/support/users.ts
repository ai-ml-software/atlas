/** Accounts used by the suite (README §1). One saved session per role. */
export const USERS = {
  admin:      { email: 'admin@hospitalityacademy.sa',          password: 'admin123' },
  instructor: { email: 'instructor.fo@hospitalityacademy.sa',  password: 'Academy#2026' },
  student:    { email: 'omar.learner@dyafagroup.sa',           password: 'Academy#2026' },
  orgAdmin:   { email: 'org.admin@dyafagroup.sa',              password: 'Academy#2026' },
  gm:         { email: 'demo.gm@altusdemo.sa',                 password: 'Academy#2026' },
  supervisor: { email: 'demo.supervisor@altusdemo.sa',         password: 'Academy#2026' },
  training:   { email: 'demo.training@altusdemo.sa',           password: 'Academy#2026' },
  exec:       { email: 'demo.exec@altusdemo.sa',               password: 'Academy#2026' },
  learner:    { email: 'demo.learner@altusdemo.sa',            password: 'Academy#2026' },
} as const;

export type Role = keyof typeof USERS;
export const stateFile = (role: Role) => `.auth/${role}.json`;
