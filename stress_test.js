import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    vus: 60,
    duration: '30s',
};

export default function () {
    // Port 8000 par hit karo
    const url = 'http://host.docker.internal:8000/api/load-test-companies';
    
    const params = {
        headers: {
            'Accept': 'application/json',
        },
    };

    const res = http.get(url, params);

    check(res, {
        'status is 200': (r) => r.status === 200,
    });

    sleep(0.1); // Fast testing ke liye sleep kam kar diya
}