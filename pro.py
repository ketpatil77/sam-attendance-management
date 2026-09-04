import matplotlib.pyplot as plt
import numpy as np

time = np.array([1.8, 2.0, 2.2, 2.4, 2.6, 3.0])
PI_speed = [100, 80, 85, 90, 95, 98]
PID_speed = [100, 95, 98, 99, 100, 100]

plt.figure(figsize=(10, 6))
plt.plot(time, PI_speed, 'r--', linewidth=2, label='PI Controller')
plt.plot(time, PID_speed, 'b-', linewidth=2, label='PID Controller')
plt.xlabel('Time (s)')
plt.ylabel('Speed (rad/s)')
plt.title('Load Disturbance Rejection (T_L = 1 Nm at t=2s)')
plt.axvline(x=2, color='k', linestyle=':', label='Disturbance Applied')
plt.legend()
plt.grid(True)
plt.show()