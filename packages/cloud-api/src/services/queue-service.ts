import { QueueJob } from '@wp-content-flow/shared-types';

export class QueueService {
  async addJob(job: QueueJob): Promise<void> {
    // TODO: Implement queue job addition
    console.log('Adding job to queue:', job.id);
  }
}