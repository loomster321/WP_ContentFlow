import { Router } from 'express';

const router = Router();

router.get('/', (req, res) => {
  res.json({ message: 'Knowledge base endpoint placeholder' });
});

export default router;