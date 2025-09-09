import { Router } from 'express';

const router = Router();

router.get('/', (req, res) => {
  res.json({ message: 'Workflows endpoint placeholder' });
});

export default router;